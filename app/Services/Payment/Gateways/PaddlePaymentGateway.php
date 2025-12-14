<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\{
    User,
    Package,
    Order,
    PaymentGateways,
    UserLicence
};
use App\Services\{
    License\LicenseApiService,
    FirstPromoterService
};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaddlePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected LicenseApiService $licenseApiService,
        protected FirstPromoterService $firstPromoterService
    ) {}

    public function getName(): string
    {
        return 'Paddle';
    }

    protected function getPaymentGatewayId(): ?int
    {
        return PaymentGateways::where('name', 'Paddle')->value('id');
    }

    protected function getApiBaseUrl(): string
    {
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        return $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';
    }

    protected function getApiKey(): string
    {
        return config('payment.gateways.Paddle.api_key');
    }

    public function ensurePaddleCustomerId(User $user): ?string
    {
        if ($user->paddle_customer_id) {
            return $user->paddle_customer_id;
        }

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        $existingCustomerResponse = Http::withHeaders($headers)
            ->get("{$apiBaseUrl}/customers", ['email' => $user->email]);

        if ($existingCustomerResponse->successful()) {
            $customers = $existingCustomerResponse->json()['data'] ?? [];
            if (!empty($customers)) {
                $customerId = $customers[0]['id'];
                $user->update(['paddle_customer_id' => $customerId]);
                return $customerId;
            }
        }

        $customerData = [
            'email' => $user->email,
            'name' => $user->name ?: ($user->first_name && $user->last_name ? $user->first_name . ' ' . $user->last_name : 'User'),
            'custom_data' => ['user_id' => (string) $user->id]
        ];

        if (empty($customerData['name']) || trim($customerData['name']) === '') {
            $customerData['name'] = 'User';
        }

        $customerResponse = Http::withHeaders($headers)->post("{$apiBaseUrl}/customers", $customerData);

        if (!$customerResponse->successful()) {
            $responseData = $customerResponse->json();
            if ($customerResponse->status() === 409 && isset($responseData['error']['code']) && $responseData['error']['code'] === 'customer_already_exists') {
                if (isset($responseData['error']['detail']) && preg_match('/customer of id ([a-zA-Z0-9_]+)/', $responseData['error']['detail'], $matches)) {
                    $customerId = $matches[1];
                    $user->update(['paddle_customer_id' => $customerId]);
                    return $customerId;
                }
            }
            return null;
        }

        $customerData = $customerResponse->json();
        if (isset($customerData['data']['id'])) {
            $customerId = $customerData['data']['id'];
            $user->update(['paddle_customer_id' => $customerId]);
            return $customerId;
        }

        return null;
    }

    public function createCheckout(User $user, Package $package, array $options = []): array
    {
        $isUpgrade = $options['is_upgrade'] ?? false;
        $isDowngrade = $options['is_downgrade'] ?? false;

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'transaction_id' => 'PADDLE-PENDING-' . Str::random(10),
            'payment_gateway_id' => $this->getPaymentGatewayId(),
            'status' => 'pending',
            'metadata' => [
                'is_upgrade' => $isUpgrade,
                'is_downgrade' => $isDowngrade
            ]
        ]);

        $this->ensurePaddleCustomerId($user);

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $productsResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

        if (!$productsResponse->successful()) {
            throw new \Exception('Failed to fetch Paddle products');
        }

        $products = $productsResponse->json()['data'];
        $matchingProduct = collect($products)->firstWhere('name', $package->name);

        if (!$matchingProduct) {
            throw new \Exception("Package '{$package->name}' not found in Paddle products");
        }

        $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
        if (!$price) {
            throw new \Exception('No active price found for package');
        }

        $transactionData = [
            'items' => [['price_id' => $price['id'], 'quantity' => 1]],
            'customer_id' => $user->paddle_customer_id,
            'currency_code' => 'USD',
            'custom_data' => [
                'user_id' => (string) $user->id,
                'package_id' => (string) $package->id,
                'package' => $package->name,
                'order_id' => (string) $order->id,
                'action' => $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new')
            ],
            'checkout' => [
                'settings' => ['display_mode' => 'overlay'],
                'success_url' => route('payments.success', ['gateway' => 'paddle', 'transaction_id' => '{transaction_id}']),
                'cancel_url' => route('payments.popup-cancel')
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$apiBaseUrl}/transactions", $transactionData);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Paddle checkout: ' . $response->body());
        }

        $transaction = $response->json()['data'];

        $order->update([
            'transaction_id' => $transaction['id'],
            'metadata' => array_merge($order->metadata ?? [], [
                'paddle_transaction_id' => $transaction['id'],
                'checkout_url' => $transaction['checkout']['url']
            ])
        ]);

        return [
            'success' => true,
            'checkout_url' => $transaction['checkout']['url'],
            'transaction_id' => $transaction['id'],
            'order_id' => $order->id
        ];
    }

    public function processPayment(array $paymentData, bool $returnRedirect = true)
    {
        return DB::transaction(function () use ($paymentData, $returnRedirect) {
            $userId = $paymentData['user_id'] ?? (isset($paymentData['custom_data']) ? ($paymentData['custom_data']['user_id'] ?? null) : null);
            $packageName = ucfirst($paymentData['package']) ?? (isset($paymentData['custom_data']) ? (ucfirst($paymentData['custom_data']['package']) ?? null) : null);
            $transactionId = $paymentData['order_id'] ?? $paymentData['order'] ?? ($paymentData['id'] ?? null);
            $amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : ($paymentData['total'] ?? (isset($paymentData['items'][0]) ? ($paymentData['items'][0]['subtotal'] / 100 ?? 0) : 0));
            $subscriptionId = $paymentData['subscription_id'] ?? null;
            $action = $paymentData['action'] ?? (isset($paymentData['custom_data']) ? ($paymentData['custom_data']['action'] ?? 'new') : 'new');
            $currency = $paymentData['currency'] ?? 'USD';

            if (!$userId || !$packageName) {
                throw new \Exception('Invalid payment data');
            }

            $user = User::find($userId);
            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();

            if (!$user || !$package) {
                throw new \Exception('Invalid payment data');
            }

            $existingOrder = Order::where('transaction_id', $transactionId)->first();
            if ($existingOrder && $existingOrder->status === 'completed') {
                return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => 'Payment already processed'] : true;
            }

            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_gateway_id' => $this->getPaymentGatewayId(),
                    'order_type' => $action,
                    'status' => 'pending',
                    'metadata' => $paymentData
                ]
            );

            $license = null;
            if ($subscriptionId) {
                $existingLicense = \App\Models\UserLicence::where('user_id', $user->id)
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
                    ->first();
                if ($existingLicense) {
                    $license = $existingLicense;
                }
            }

            if (!$license) {
                $license = $this->licenseApiService->createAndActivateLicense(
                    $user,
                    $package,
                    $subscriptionId,
                    $this->getPaymentGatewayId(),
                    $action === 'upgrade'
                );

                if (!$license) {
                    throw new \Exception('License generation failed');
                }
            }

            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId(),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            $order->update(['status' => 'completed']);

            if (config('payment.firstpromoter.enabled', false) && $amount > 0) {
                $this->trackFirstPromoterSale($order, $user, $package, $paymentData);
            }

            return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => "Subscription to {$package->name} bought successfully!"] : true;
        });
    }

    protected function trackFirstPromoterSale(Order $order, User $user, Package $package, array $paymentData): void
    {
        try {
            $metadata = $order->metadata ?? [];
            $customData = $paymentData['custom_data'] ?? [];

            $tid = $metadata['fp_tid'] ?? $metadata['tid'] ?? $customData['fp_tid'] ?? $customData['tid'] ?? null;
            $refId = $metadata['ref_id'] ?? $customData['ref_id'] ?? null;

            if (!$tid && !$refId && isset($paymentData['metadata']['custom_data'])) {
                $transactionCustomData = $paymentData['metadata']['custom_data'];
                $tid = $transactionCustomData['fp_tid'] ?? $transactionCustomData['tid'] ?? null;
                $refId = $transactionCustomData['ref_id'] ?? null;
            }

            $trackingData = [
                'email' => $user->email,
                'event_id' => $order->transaction_id,
                'amount' => $order->amount,
                'currency' => $order->currency ?? 'USD',
                'plan' => $package->name,
            ];

            if ($tid) {
                $trackingData['tid'] = $tid;
            }

            if ($refId) {
                $trackingData['ref_id'] = $refId;
            }

            $response = $this->firstPromoterService->trackSale($trackingData);

            if ($response) {
                $currentMetadata = $order->metadata ?? [];
                $currentMetadata['firstpromoter'] = isset($response['duplicate']) && $response['duplicate']
                    ? [
                        'duplicate' => true,
                        'message' => $response['message'] ?? 'Sale already tracked',
                        'event_id' => $trackingData['event_id'],
                        'tracked_at' => now()->toIso8601String(),
                    ]
                    : [
                        'sale_id' => $response['id'] ?? null,
                        'sale_amount' => $response['sale_amount'] ?? null,
                        'referral_id' => $response['referral']['id'] ?? null,
                        'referral_email' => $response['referral']['email'] ?? null,
                        'commissions' => array_map(function ($commission) {
                            return [
                                'id' => $commission['id'] ?? null,
                                'status' => $commission['status'] ?? null,
                                'amount' => $commission['amount'] ?? null,
                                'promoter_email' => $commission['promoter_campaign']['promoter']['email'] ?? null,
                                'campaign_name' => $commission['promoter_campaign']['campaign']['name'] ?? null,
                            ];
                        }, $response['commissions'] ?? []),
                        'tracked_at' => now()->toIso8601String(),
                    ];

                $order->update(['metadata' => $currentMetadata]);
            }
        } catch (\Exception $e) {
            Log::error('FirstPromoter: Error tracking sale', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'user_id' => $user->id
            ]);
        }
    }

    public function processWebhook(array $payload): array
    {
        $eventType = $payload['event_type'] ?? null;
        $eventData = $payload['data'] ?? [];

        if (!$eventType) {
            return ['error' => 'Invalid webhook payload'];
        }

        return match ($eventType) {
            'transaction.completed', 'transaction.paid' => $this->handleTransactionCompleted($eventData),
            'subscription.created', 'subscription.updated' => $this->handleSubscriptionEvent($eventData),
            'subscription.cancelled' => $this->handleSubscriptionCancelled($eventData),
            default => ['status' => 'ignored']
        };
    }

    protected function handleTransactionCompleted(array $eventData): array
    {
        $transactionId = $eventData['id'] ?? null;
        $customData = $eventData['custom_data'] ?? [];

        if (!$transactionId) {
            return ['error' => 'Missing transaction ID'];
        }

        $order = Order::where('transaction_id', $transactionId)->first();
        if ($order && $order->status === 'completed') {
            return ['status' => 'already_processed', 'order_id' => $order->id];
        }

        $userId = $customData['user_id'] ?? null;
        $packageName = $customData['package'] ?? null;

        if (!$userId || !$packageName) {
            return ['error' => 'Missing required data'];
        }

        $pendingOrder = Order::where('transaction_id', $transactionId)
            ->where('status', 'pending')
            ->first();

        if ($pendingOrder) {
            $result = $this->processPendingOrder($pendingOrder, $eventData, $userId);
            return ['status' => $result['success'] ? 'processed' : 'failed'];
        }

        $amount = $eventData['details']['totals']['total'] / 100;
        $currency = $eventData['currency_code'];
        $subscriptionId = $eventData['subscription_id'] ?? 'PADDLE-' . $transactionId;

        $paymentData = [
            'user_id' => $userId,
            'package' => $packageName,
            'order_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'subscription_id' => $subscriptionId,
            'custom_data' => $customData,
            'metadata' => $eventData
        ];

        $result = $this->processPayment($paymentData, false);
        return ['status' => $result ? 'processed' : 'failed'];
    }

    protected function handleSubscriptionEvent(array $eventData): array
    {
        $subscriptionId = $eventData['id'] ?? null;
        $customData = $eventData['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;

        if (!$subscriptionId || !$userId) {
            return ['status' => 'incomplete_data'];
        }

        $user = User::find($userId);
        if ($user) {
            $user->update(['subscription_id' => $subscriptionId]);

            $pendingOrder = Order::where('metadata->subscription_id', $subscriptionId)
                ->whereIn('status', ['pending', 'pending_upgrade'])
                ->first();

            if ($pendingOrder) {
                $pendingOrder->update(['status' => 'completed']);
                $package = Package::find($pendingOrder->package_id);

                if ($package) {
                    $user->update([
                        'package_id' => $package->id,
                        'is_subscribed' => true
                    ]);

                    $this->licenseApiService->createAndActivateLicense(
                        $user,
                        $package,
                        $subscriptionId,
                        $pendingOrder->payment_gateway_id
                    );
                }
            }
        }

        return ['status' => 'processed'];
    }

    protected function handleSubscriptionCancelled(array $eventData): array
    {
        $subscriptionId = $eventData['id'] ?? null;

        if (!$subscriptionId) {
            return ['status' => 'no_subscription_id'];
        }

        $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
        $user = $userLicense ? $userLicense->user : User::where('subscription_id', $subscriptionId)->first();

        if (!$user) {
            return ['status' => 'user_not_found'];
        }

        DB::transaction(function () use ($user, $subscriptionId, $userLicense) {
            if ($userLicense) {
                $userLicense->delete();
            }

            $user->update([
                'is_subscribed' => false,
                'subscription_id' => null,
                'package_id' => null,
                'payment_gateway_id' => null,
                'user_license_id' => null
            ]);

            $orders = Order::where('user_id', $user->id)
                ->where('status', 'cancellation_scheduled')
                ->get();

            foreach ($orders as $order) {
                $order->update(['status' => 'canceled']);
            }

            $specificOrders = Order::where('user_id', $user->id)
                ->where('metadata->subscription_id', $subscriptionId)
                ->where('status', '!=', 'canceled')
                ->get();

            foreach ($specificOrders as $order) {
                $order->update(['status' => 'canceled']);
            }
        });

        return ['status' => 'processed'];
    }

    protected function processPendingOrder(Order $order, array $transactionData, int $userId)
    {
        $order->update(['status' => 'completed']);

        $user = User::find($userId);
        $package = Package::find($order->package_id);

        if (!$user || !$package) {
            return ['success' => false, 'error' => 'Order processing failed'];
        }

        $subscriptionId = $transactionData['subscription_id'] ?? ($order->metadata['subscription_id'] ?? null) ?? null;

        if (!$subscriptionId) {
            $subscriptionId = 'PADDLE-' . $transactionData['id'];
        }

        $user->update([
            'package_id' => $package->id,
            'is_subscribed' => true,
            'payment_gateway_id' => $order->payment_gateway_id,
            'subscription_id' => $subscriptionId
        ]);

        $license = $this->licenseApiService->createAndActivateLicense(
            $user,
            $package,
            $subscriptionId,
            $order->payment_gateway_id
        );

        if ($license) {
            $customData = $transactionData['custom_data'] ?? [];
            $isUpgrade = ($customData['action'] ?? '') === 'upgrade' || $order->order_type === 'upgrade';

            if (config('payment.firstpromoter.enabled', false) && $order->amount > 0) {
                $paymentData = [
                    'custom_data' => $customData,
                    'metadata' => $transactionData
                ];
                $this->trackFirstPromoterSale($order, $user, $package, $paymentData);
            }

            return [
                'success' => true,
                'user' => $user,
                'package' => $package,
                'is_upgrade' => $isUpgrade,
                'license' => $license
            ];
        }

        return ['success' => false, 'error' => 'Failed to create license'];
    }

    public function processSuccessCallback(array $requestData): array
    {
        $transactionId = $requestData['transaction_id'] ?? $requestData['transactionId'] ?? null;

        if (!$transactionId) {
            return ['success' => false, 'error' => 'Missing transaction ID'];
        }

        $transactionData = $this->verifyTransaction($transactionId);

        if (!$transactionData || !in_array($transactionData['status'], ['completed', 'paid'])) {
            return ['success' => false, 'error' => 'Payment verification failed'];
        }

        $customData = $transactionData['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;

        if (!$userId) {
            return ['success' => false, 'error' => 'Invalid transaction data'];
        }

        $order = Order::where('transaction_id', $transactionId)->first();
        if ($order && $order->status === 'completed') {
            return ['success' => true, 'already_completed' => true, 'user_id' => $userId];
        }

        $pendingOrder = Order::where('transaction_id', $transactionId)
            ->whereIn('status', ['pending', 'pending_upgrade'])
            ->first();

        if ($pendingOrder) {
            return $this->processPendingOrder($pendingOrder, $transactionData, $userId);
        }

        $packageName = $customData['package'] ?? null;
        if (!$packageName) {
            return ['success' => false, 'error' => 'Invalid transaction data'];
        }

        $amount = $transactionData['details']['totals']['total'] / 100;
        $currency = $transactionData['currency_code'];
        $subscriptionId = $transactionData['subscription_id'] ?? 'PADDLE-' . $transactionId;

        $paymentData = [
            'user_id' => $userId,
            'package' => $packageName,
            'order_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'subscription_id' => $subscriptionId,
            'custom_data' => $customData,
            'metadata' => $transactionData
        ];

        $result = $this->processPayment($paymentData, false);
        return ['success' => $result, 'user_id' => $userId, 'package_name' => $packageName];
    }

    public function createUpgradeCheckout(User $user, Package $package, string $subscriptionId): string
    {
        $product = $this->findProductByName($package->name);
        if (!$product) {
            throw new \Exception('Paddle product not found for upgrade');
        }
        $price = $this->findActivePriceForProduct($product['id']);
        if (!$price) {
            throw new \Exception('No active price found for upgrade');
        }
        $priceId = $price['id'];

        $tempTransactionId = 'PADDLE-UPGRADE-' . Str::random(10);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId(),
            'order_type' => 'upgrade',
            'transaction_id' => $tempTransactionId,
            'metadata' => [
                'original_package' => $user->package->name ?? 'Unknown',
                'upgrade_to' => $package->name,
                'upgrade_type' => 'subscription_upgrade',
                'price_id' => $priceId,
                'subscription_id' => $subscriptionId
            ]
        ]);

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $requestData = [
            'items' => [['price_id' => $priceId, 'quantity' => 1]],
            'customer_id' => $user->paddle_customer_id,
            'currency_code' => 'USD',
            'custom_data' => [
                'user_id' => (string) $user->id,
                'package_id' => (string) $package->id,
                'package' => $package->name,
                'order_id' => (string) $order->id,
                'action' => 'upgrade'
            ],
            'proration_billing_mode' => 'prorated_immediately',
            'checkout' => [
                'settings' => ['display_mode' => 'overlay'],
                'success_url' => route('payments.success', [
                    'gateway' => 'paddle',
                    'transaction_id' => '{transaction_id}',
                    'upgrade' => 'true',
                    'order_id' => $order->id
                ]),
                'cancel_url' => route('payments.popup-cancel')
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$apiBaseUrl}/transactions", $requestData);

        if (!$response->successful()) {
            $order->delete();
            throw new \Exception('Failed to create Paddle upgrade checkout');
        }

        $data = $response->json();
        $checkoutUrl = $data['data']['checkout']['url'] ?? null;

        if (!$checkoutUrl) {
            $order->delete();
            throw new \Exception('No checkout URL in Paddle response');
        }

        $realTransactionId = $data['data']['id'] ?? null;
        if ($realTransactionId) {
            $order->update([
                'transaction_id' => $realTransactionId,
                'metadata' => array_merge($order->metadata ?? [], [
                    'paddle_transaction_id' => $realTransactionId,
                    'checkout_url' => $checkoutUrl
                ])
            ]);
        }

        return $checkoutUrl;
    }

    public function createDowngradeCheckout(User $user, Package $package, string $subscriptionId): string
    {
        $product = $this->findProductByName($package->name);
        if (!$product) {
            throw new \Exception('Paddle product not found for downgrade');
        }
        $price = $this->findActivePriceForProduct($product['id']);
        if (!$price) {
            throw new \Exception('No active price found for downgrade');
        }
        $priceId = $price['id'];

        $tempTransactionId = 'PADDLE-DOWNGRADE-' . Str::random(10);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId(),
            'order_type' => 'downgrade',
            'transaction_id' => $tempTransactionId,
            'metadata' => [
                'original_package' => ($user->userLicence && $user->userLicence->package) ? $user->userLicence->package->name : ($user->package->name ?? 'Unknown'),
                'downgrade_to' => $package->name,
                'downgrade_type' => 'subscription_downgrade',
                'price_id' => $priceId,
                'subscription_id' => $subscriptionId
            ]
        ]);

        if (!$user->paddle_customer_id) {
            $order->delete();
            throw new \Exception('Paddle customer ID missing');
        }

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $requestData = [
            'items' => [['price_id' => $priceId, 'quantity' => 1]],
            'customer_id' => $user->paddle_customer_id,
            'currency_code' => 'USD',
            'custom_data' => [
                'user_id' => (string) $user->id,
                'package_id' => (string) $package->id,
                'package' => $package->name,
                'order_id' => (string) $order->id,
                'action' => 'downgrade'
            ],
            'proration_billing_mode' => 'prorated_immediately',
            'checkout' => [
                'settings' => ['display_mode' => 'overlay'],
                'success_url' => route('payments.success', [
                    'gateway' => 'paddle',
                    'transaction_id' => '{transaction_id}',
                    'downgrade' => 'true',
                    'order_id' => $order->id
                ]),
                'cancel_url' => route('payments.popup-cancel')
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$apiBaseUrl}/transactions", $requestData);

        if (!$response->successful()) {
            $order->delete();
            throw new \Exception('Failed to create Paddle downgrade checkout');
        }

        $data = $response->json();
        $checkoutUrl = $data['data']['checkout']['url'] ?? null;

        if (!$checkoutUrl) {
            $order->delete();
            throw new \Exception('No checkout URL in Paddle response');
        }

        $realTransactionId = $data['data']['id'] ?? null;
        if ($realTransactionId) {
            $order->update([
                'transaction_id' => $realTransactionId,
                'metadata' => array_merge($order->metadata ?? [], [
                    'paddle_transaction_id' => $realTransactionId,
                    'checkout_url' => $checkoutUrl
                ])
            ]);
        }

        return $checkoutUrl;
    }

    public function downgradeSubscriptionForUser(User $user, string $subscriptionId, string $newProductId)
    {
        return $this->downgradeSubscription($subscriptionId, $newProductId);
    }

    public function cancelSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null): array
    {
        $subscription = $user->getPaddleSubscription('main');
        if ($subscription && $subscription->paddle_id === $subscriptionId) {
            $subscription->cancel();

            // Update user's license status
            if ($user->userLicence) {
                $user->userLicence->update([
                    'status' => 'cancelled_at_period_end',
                    'cancelled_at' => now(),
                ]);
            }

            Order::create([
                'user_id' => $user->id,
                'package_id' => $user->package_id,
                'order_type' => 'cancellation',
                'status' => 'cancellation_scheduled',
                'transaction_id' => 'CANCEL-' . $user->id . '-' . uniqid(),
                'amount' => 0,
                'currency' => $user->currency ?? 'USD',
                'payment_method' => 'Paddle',
                'metadata' => [
                    'original_subscription_id' => $subscriptionId,
                    'scheduled_termination_date' => $user->userLicence->expires_at->toDateTimeString() ?? null,
                ]
            ]);

            return ['success' => true, 'message' => 'Cancellation scheduled at period end'];
        }

        // Fall back to API-based cancellation
        $response = $this->cancelPaddleSubscription($subscriptionId, 1);

        if ($response) {
            // Update user's license status
            if ($user->userLicence) {
                $user->userLicence->update([
                    'status' => 'cancelled_at_period_end',
                    'cancelled_at' => now(),
                ]);
            }

            Order::create([
                'user_id' => $user->id,
                'package_id' => $user->package_id,
                'order_type' => 'cancellation',
                'status' => 'cancellation_scheduled',
                'transaction_id' => 'CANCEL-' . $user->id . '-' . uniqid(),
                'amount' => 0,
                'currency' => $user->currency ?? 'USD',
                'payment_method' => 'Paddle',
                'metadata' => [
                    'original_subscription_id' => $subscriptionId,
                    'scheduled_termination_date' => $user->userLicence->expires_at->toDateTimeString() ?? null,
                ]
            ]);

            return ['success' => true, 'message' => 'Cancellation scheduled'];
        }

        return ['success' => false, 'message' => 'Cancellation failed'];
    }

    public function upgradeSubscription(string $subscriptionId, string $newPriceId, string $prorationBillingMode = null)
    {
        $user = User::where('subscription_id', $subscriptionId)
            ->orWhereHas('userLicence', function($query) use ($subscriptionId) {
                $query->where('subscription_id', $subscriptionId);
            })
            ->first();

        if ($user) {
            $subscription = $user->getPaddleSubscription('main');
            if ($subscription && $subscription->paddle_id === $subscriptionId) {
                $subscription->swapAndInvoice($newPriceId);
                return [
                    'success' => true,
                    'proration' => true,
                    'scheduled_change' => null
                ];
            }
        }

        // Fall back to API-based upgrade
        $payload = [
            'items' => [['price_id' => $newPriceId, 'quantity' => 1]]
        ];

        if ($prorationBillingMode) {
            $payload['proration_billing_mode'] = $prorationBillingMode;
        }

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->patch("{$apiBaseUrl}/subscriptions/{$subscriptionId}", $payload);

        if (!$response->successful()) {
            Log::error('Paddle subscription upgrade failed', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newPriceId, string $prorationBillingMode = null)
    {
        $user = User::where('subscription_id', $subscriptionId)
            ->orWhereHas('userLicence', function($query) use ($subscriptionId) {
                $query->where('subscription_id', $subscriptionId);
            })
            ->first();

        if ($user) {
            $subscription = $user->getPaddleSubscription('main');
            if ($subscription && $subscription->paddle_id === $subscriptionId) {
                $subscription->swap($newPriceId);
                return [
                    'success' => true,
                    'proration' => null,
                    'scheduled_change' => true
                ];
            }
        }

        // Fall back to API-based downgrade
        $payload = [
            'items' => [['price_id' => $newPriceId, 'quantity' => 1]]
        ];

        if ($prorationBillingMode) {
            $payload['proration_billing_mode'] = $prorationBillingMode;
        }

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->patch("{$apiBaseUrl}/subscriptions/{$subscriptionId}", $payload);

        if (!$response->successful()) {
            Log::error('Paddle subscription downgrade failed', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json();
    }

    protected function cancelPaddleSubscription(string $subscriptionId, int $billingPeriod = 1)
    {
        $effectiveFrom = $billingPeriod === 0 ? 'immediately' : 'next_billing_period';

        Log::info('Canceling Paddle subscription', [
            'subscription_id' => $subscriptionId,
            'effective_from' => $effectiveFrom,
            'billing_period' => $billingPeriod
        ]);

        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => $effectiveFrom
        ]);

        if (!$response->successful()) {
            Log::error('Paddle subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'effective_from' => $effectiveFrom,
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);
            return null;
        }

        $responseData = $response->json();

        Log::info('Paddle subscription cancellation successful', [
            'subscription_id' => $subscriptionId,
            'effective_from' => $effectiveFrom,
            'response_data' => $responseData
        ]);

        return $responseData;
    }

    public function getSubscription(string $subscriptionId)
    {
        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$apiBaseUrl}/subscriptions/{$subscriptionId}");

        if (!$response->successful()) {
            Log::error('Failed to get Paddle subscription', [
                'subscription_id' => $subscriptionId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json()['data'] ?? null;
    }

    public function getProducts()
    {
        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

        if (!$response->successful()) {
            Log::error('Failed to get Paddle products', [
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json()['data'] ?? [];
    }

    public function findProductByName(string $productName)
    {
        $products = $this->getProducts();

        if (!$products) {
            return null;
        }

        return collect($products)->firstWhere('name', $productName);
    }

    public function findActivePriceForProduct(string $productId)
    {
        $products = $this->getProducts();

        if (!$products) {
            return null;
        }

        $product = collect($products)->firstWhere('id', $productId);

        if (!$product) {
            return null;
        }

        return collect($product['prices'])->firstWhere('status', 'active');
    }

    public function verifyTransaction(string $transactionId): ?array
    {
        $apiKey = $this->getApiKey();
        $apiBaseUrl = $this->getApiBaseUrl();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey
        ])->get("{$apiBaseUrl}/transactions/{$transactionId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['data'];
    }

    public function verifyOrder(string $transactionId, int $userId): array
    {
        $order = Order::where('transaction_id', $transactionId)
            ->where('user_id', $userId)
            ->first();

        if ($order && $order->status === 'completed') {
            return ['success' => true, 'status' => 'completed', 'message' => 'Order already processed'];
        }

        $transactionData = $this->verifyTransaction($transactionId);

        if (!$transactionData) {
            return ['success' => false, 'error' => 'Transaction verification failed'];
        }

        if (!in_array($transactionData['status'], ['completed', 'paid'])) {
            return ['success' => false, 'status' => $transactionData['status'], 'message' => 'Transaction not yet completed'];
        }

        if (!$order || $order->status !== 'completed') {
            $customData = $transactionData['custom_data'] ?? [];
            $packageName = $customData['package'] ?? null;

            if (!$packageName) {
                return ['success' => false, 'error' => 'Invalid transaction data'];
            }

            $result = $this->processPaymentFromWebhook($transactionData, $packageName, $userId);
            return ['success' => $result, 'status' => 'completed', 'message' => $result ? 'Order processed successfully' : 'Order processing failed'];
        }

        return ['success' => true, 'status' => 'completed', 'message' => 'Order verified successfully'];
    }

    protected function processPaymentFromWebhook(array $transactionData, string $packageName, int $userId): bool
    {
        return DB::transaction(function () use ($transactionData, $packageName, $userId) {
            $transactionId = $transactionData['id'];
            $user = User::find($userId);
            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();

            if (!$user || !$package) {
                throw new \Exception("User or package not found");
            }

            $amount = $transactionData['details']['totals']['total'] / 100;
            $currency = $transactionData['currency_code'];

            $existingOrder = Order::where('transaction_id', $transactionId)->first();
            if ($existingOrder && $existingOrder->status === 'completed') {
                return true;
            }

            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_gateway_id' => $this->getPaymentGatewayId(),
                    'status' => 'completed',
                    'metadata' => $transactionData
                ]
            );

            $subscriptionId = $transactionData['subscription_id'] ?? 'PADDLE-' . $transactionId;

            $existingLicense = UserLicence::where('user_id', $user->id)
                ->where('subscription_id', $subscriptionId)
                ->where('is_active', true)
                ->first();

            if (!$existingLicense) {
                $license = $this->licenseApiService->createAndActivateLicense(
                    $user,
                    $package,
                    $subscriptionId,
                    $this->getPaymentGatewayId()
                );

                if (!$license) {
                    throw new \Exception('License generation failed');
                }
            }

            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId(),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            if (config('payment.firstpromoter.enabled', false) && $amount > 0) {
                $customData = $transactionData['custom_data'] ?? [];
                $metadata = $transactionData;
                $tid = $metadata['fp_tid'] ?? $metadata['tid'] ?? $customData['fp_tid'] ?? $customData['tid'] ?? null;
                $refId = $metadata['ref_id'] ?? $customData['ref_id'] ?? null;

                $trackingData = [
                    'email' => $user->email,
                    'event_id' => $order->transaction_id,
                    'amount' => $order->amount,
                    'currency' => $order->currency ?? 'USD',
                    'plan' => $package->name,
                ];

                if ($tid) {
                    $trackingData['tid'] = $tid;
                }

                if ($refId) {
                    $trackingData['ref_id'] = $refId;
                }

                $this->firstPromoterService->trackSale($trackingData);
            }

            return true;
        });
    }
}
