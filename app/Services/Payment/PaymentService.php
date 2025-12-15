<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\Package;
use App\Models\User;
use App\Models\UserLicence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Factories\PaymentGatewayFactory;
use App\Services\SubscriptionService;
use App\Services\FirstPromoterService;
use App\Services\TenantAssignmentService;
use App\Services\PasswordBindingService;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    protected $gateway;
    protected $config;
    protected $licenseApiService;
    protected $subscriptionService;
    protected $firstPromoterService;
    protected $paymentGatewayFactory;
    protected $tenantAssignmentService;
    protected $passwordBindingService;

    public function __construct(
        \App\Services\License\LicenseApiService $licenseApiService,
        SubscriptionService $subscriptionService,
        FirstPromoterService $firstPromoterService,
        PaymentGatewayFactory $paymentGatewayFactory,
        TenantAssignmentService $tenantAssignmentService,
        PasswordBindingService $passwordBindingService
    ) {
        $this->licenseApiService = $licenseApiService;
        $this->subscriptionService = $subscriptionService;
        $this->firstPromoterService = $firstPromoterService;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
        $this->tenantAssignmentService = $tenantAssignmentService;
        $this->passwordBindingService = $passwordBindingService;
        $this->gateway = config('payment.default_gateway');
        $this->config = config('payment.gateways.' . $this->gateway);
    }

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->config = config('payment.gateways.' . $gateway);
        Log::info("PaymentService gateway set to: {$this->gateway}");
        return $this;
    }

    public function initializeGateway(Order $order)
    {
        $user = $order->user;

        if ($user->payment_gateway_id) {
            $paymentGateway = PaymentGateways::find($user->payment_gateway_id);
            if ($paymentGateway) {
                $this->setGateway($paymentGateway->name);
                Log::info("Active Gateway Selected: {$paymentGateway->name}");
                return;
            } else {
                Log::warning("Invalid payment gateway ID for user: {$user->payment_gateway_id}");
            }
        }

        $activeGateway = PaymentGateways::where('is_active', true)->first();
        if (!$activeGateway) {
            throw new \Exception("No active payment gateway configured");
        }

        Log::info("Active Gateway Selected: {$activeGateway->name}");
        $user->update(['payment_gateway_id' => $activeGateway->id]);
        $this->setGateway($activeGateway->name);
    }

    // ==================== CHECKOUT CREATION ====================

    public function createPaddleCheckout(User $user, Package $package, array $options = [])
    {
        $isUpgrade = $options['is_upgrade'] ?? false;
        $isDowngrade = $options['is_downgrade'] ?? false;

        // Create pending order
        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'transaction_id' => 'PADDLE-PENDING-' . Str::random(10),
            'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
            'status' => 'pending',
            'metadata' => [
                'is_upgrade' => $isUpgrade,
                'is_downgrade' => $isDowngrade
            ]
        ]);

        // Ensure user has Paddle customer ID
        $this->ensurePaddleCustomerId($user);

        // Get product and price from Paddle
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

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

        // Create transaction
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


    public function createPayProGlobalCheckout(User $user, Package $package, array $options = [])
    {
        $isUpgrade = $options['is_upgrade'] ?? false;
        $isDowngrade = $options['is_downgrade'] ?? false;
        $processedPackage = strtolower(str_replace('-plan', '', $package->name));

        $productId = config("payment.gateways.PayProGlobal.product_ids.{$processedPackage}");
        if (!$productId) {
            throw new \Exception("PayProGlobal product ID not configured for package: {$processedPackage}");
        }

        $pendingOrderId = 'PPG-PENDING-' . Str::random(10);
        $orderMetadata = [
            'package' => $processedPackage,
            'pending_order_id' => $pendingOrderId,
            'action' => $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new')
        ];

        if (isset($options['fp_tid']) && $options['fp_tid']) {
            $orderMetadata['fp_tid'] = $options['fp_tid'];
        }

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'transaction_id' => $pendingOrderId,
            'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
            'status' => 'pending',
            'metadata' => $orderMetadata
        ]);

        // Generate auth token for cross-domain redirect
        $authToken = Str::random(64);
        Cache::put("paypro_auth_token_{$authToken}", $user->id, now()->addMinutes(10));

        $successParams = [
            'gateway' => 'payproglobal',
            'user_id' => $user->id,
            'package' => $processedPackage,
            'popup' => 'true',
            'pending_order_id' => $order->transaction_id,
            'action' => $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new'),
            'auth_token' => $authToken
        ];

        $successUrl = url(route('payments.success', $successParams));

        $customData = [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'package' => $processedPackage,
            'pending_order_id' => $order->transaction_id,
            'action' => $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new')
        ];

        if (isset($options['fp_tid']) && $options['fp_tid']) {
            $customData['fp_tid'] = $options['fp_tid'];
        }

        $checkoutParams = [
            'products[1][id]' => $productId,
            'email' => $user->email,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'custom' => json_encode($customData),
            'page-template' => 'ID',
            'currency' => 'USD',
            'use-test-mode' => config('payment.gateways.PayProGlobal.test_mode', true) ? 'true' : 'false',
            'secret-key' => config('payment.gateways.PayProGlobal.webhook_secret'),
            'success-url' => $successUrl,
            'cancel-url' => route('payments.popup-cancel')
        ];

        $checkoutUrl = "https://store.payproglobal.com/checkout?" . http_build_query($checkoutParams);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'pending_order_id' => $pendingOrderId
        ];
    }

    // ==================== PAYMENT PROCESSING ====================

    public function processPayment(array $paymentData, string $gateway, bool $returnRedirect = true)
    {
        return DB::transaction(function () use ($paymentData, $gateway, $returnRedirect) {
            $userId = $paymentData['user_id'] ?? null;
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

            // Check if order already exists and is completed
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
                    'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                    'order_type' => $action,
                    'status' => 'pending',
                    'metadata' => $paymentData
                ]
            );

            // Handle downgrades - always schedule them, never apply immediately
            if ($action === 'downgrade') {
                $activeLicense = $user->userLicence;
                $scheduledActivationDate = null;

                if ($activeLicense && $activeLicense->expires_at) {
                    $scheduledActivationDate = $activeLicense->expires_at;
                } else {
                    // If no expiry date, schedule for end of current billing cycle (assume 30 days)
                    $scheduledActivationDate = now()->addDays(30);
                }

                // Cancel any existing scheduled downgrades
                Order::where('user_id', $user->id)
                    ->where('order_type', 'downgrade')
                    ->where('status', 'scheduled_downgrade')
                    ->update(['status' => 'cancelled']);

                // Update order metadata with scheduled activation date
                $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
                $orderMetadata['scheduled_activation_date'] = $scheduledActivationDate->toDateTimeString();
                $orderMetadata['original_package_id'] = $user->package_id;
                $orderMetadata['original_package_name'] = $user->package->name ?? 'Unknown';
                $orderMetadata['downgrade_processed'] = false;

                // Mark order as scheduled_downgrade instead of completed
                $order->update([
                    'status' => 'scheduled_downgrade',
                    'metadata' => $orderMetadata
                ]);

                // DO NOT update user's package_id or create new license - keep current plan active
                // The downgrade will be processed when the current plan expires

                return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.'] : true;
            }

            // For upgrades and new subscriptions, process normally
            // Check if license already exists
            $license = null;
            if ($subscriptionId) {
                $existingLicense = UserLicence::where('user_id', $user->id)
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
                    $this->getPaymentGatewayId($gateway),
                    $action === 'upgrade'
                );

                if (!$license) {
                    throw new \Exception('License generation failed');
                }
            }

            // Update user subscription status
            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            // Mark order as completed
            $order->update(['status' => 'completed']);

            // Track sale with FirstPromoter
            if (in_array($gateway, ['payproglobal', 'paddle']) && config('payment.firstpromoter.enabled', false) && $amount > 0) {
                $this->trackFirstPromoterSale($order, $user, $package, $paymentData);
            }

            return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => "Subscription to {$package->name} bought successfully!"] : true;
        });
    }

    public function processPaddlePaymentFromWebhook(array $transactionData, string $packageName, int $userId)
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
            $customData = $transactionData['custom_data'] ?? [];
            $action = $customData['action'] ?? 'new';

            // Check if order already exists
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
                    'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                    'order_type' => $action,
                    'status' => 'pending',
                    'metadata' => $transactionData
                ]
            );

            // Handle downgrades - always schedule them, never apply immediately
            if ($action === 'downgrade') {
                $activeLicense = $user->userLicence;
                $scheduledActivationDate = null;

                if ($activeLicense && $activeLicense->expires_at) {
                    $scheduledActivationDate = $activeLicense->expires_at;
                } else {
                    // If no expiry date, schedule for end of current billing cycle (assume 30 days)
                    $scheduledActivationDate = now()->addDays(30);
                }

                // Cancel any existing scheduled downgrades
                Order::where('user_id', $user->id)
                    ->where('order_type', 'downgrade')
                    ->where('status', 'scheduled_downgrade')
                    ->where('id', '!=', $order->id)
                    ->update(['status' => 'cancelled']);

                // Update order metadata with scheduled activation date
                $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
                $orderMetadata['scheduled_activation_date'] = $scheduledActivationDate->toDateTimeString();
                $orderMetadata['original_package_id'] = $user->package_id;
                $orderMetadata['original_package_name'] = $user->package->name ?? 'Unknown';
                $orderMetadata['downgrade_processed'] = false;

                // Mark order as scheduled_downgrade instead of completed
                $order->update([
                    'status' => 'scheduled_downgrade',
                    'metadata' => $orderMetadata
                ]);

                // DO NOT update user's package_id or create new license - keep current plan active
                // The downgrade will be processed when the current plan expires

                return true;
            }

            // For upgrades and new subscriptions, process normally
            $order->update(['status' => 'completed']);

            $subscriptionId = $transactionData['subscription_id'] ?? 'PADDLE-' . $transactionId;

            // Check if license exists
            $existingLicense = UserLicence::where('user_id', $user->id)
                ->where('subscription_id', $subscriptionId)
                ->where('is_active', true)
                ->first();

            if (!$existingLicense) {
                $license = $this->licenseApiService->createAndActivateLicense(
                    $user,
                    $package,
                    $subscriptionId,
                    $this->getPaymentGatewayId('paddle')
                );

                if (!$license) {
                    throw new \Exception('License generation failed');
                }
            }

            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            // Track FirstPromoter sale
            if (config('payment.firstpromoter.enabled', false) && $amount > 0) {
                $paymentData = [
                    'custom_data' => $customData,
                    'metadata' => $transactionData
                ];
                $this->trackFirstPromoterSale($order, $user, $package, $paymentData);
            }

            return true;
        });
    }

    // ==================== UPGRADE/DOWNGRADE ====================

    public function createPaddleUpgradeCheckout(User $user, Package $package, string $subscriptionId, ?string $priceId = null)
    {
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        // If priceId is not provided, fetch it
        if (!$priceId) {
            /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $paddleGateway */
            $paddleGateway = $this->paymentGatewayFactory->create('paddle');
            $product = $paddleGateway->findProductByName($package->name);
            if (!$product) {
                throw new \Exception('Paddle product not found for upgrade');
            }
            $price = $paddleGateway->findActivePriceForProduct($product['id']);
            if (!$price) {
                throw new \Exception('No active price found for upgrade');
            }
            $priceId = $price['id'];
        }

        $tempTransactionId = 'PADDLE-UPGRADE-' . Str::random(10);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
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

    public function createPaddleDowngradeCheckout(User $user, Package $package, string $subscriptionId, string $priceId)
    {
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $tempTransactionId = 'PADDLE-DOWNGRADE-' . Str::random(10);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
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

    // ==================== VERIFICATION ====================

    public function verifyPaddleTransaction(string $transactionId)
    {
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey
        ])->get("{$apiBaseUrl}/transactions/{$transactionId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['data'];
    }

    // ==================== HELPER METHODS ====================

    protected function getPaymentGatewayId(string $gatewayName): ?int
    {
        $gatewayMappings = [
            'paddle' => 'Paddle',
            'fastspring' => 'FastSpring',
            'payproglobal' => 'Pay Pro Global'
        ];

        $normalizedName = $gatewayMappings[strtolower($gatewayName)] ?? $gatewayName;
        return PaymentGateways::where('name', $normalizedName)->value('id');
    }

    public function ensurePaddleCustomerId(User $user): ?string
    {
        if ($user->paddle_customer_id) {
            return $user->paddle_customer_id;
        }

        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        // Try to find existing customer
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

        // Create new customer
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
                // Extract customer ID from error message
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

    public function trackFirstPromoterSale(Order $order, User $user, Package $package, array $paymentData): void
    {
        try {
            $metadata = $order->metadata ?? [];
            $customData = $paymentData['custom_data'] ?? [];
            $checkoutQueryString = $paymentData['checkout_query_string'] ?? null;

            $tid = null;
            $refId = null;

            if ($checkoutQueryString) {
                parse_str($checkoutQueryString, $checkoutParams);
                $customDataJson = $checkoutParams['custom'] ?? null;
                if ($customDataJson) {
                    $decodedCustom = json_decode($customDataJson, true);
                    if (!$decodedCustom) {
                        $decodedCustom = json_decode(urldecode($customDataJson), true);
                    }
                    if ($decodedCustom) {
                        $tid = $decodedCustom['fp_tid'] ?? $decodedCustom['tid'] ?? null;
                        $refId = $decodedCustom['ref_id'] ?? null;
                    }
                }
            }

            if (!$tid && !$refId) {
                $tid = $metadata['fp_tid'] ?? $metadata['tid'] ?? $customData['fp_tid'] ?? $customData['tid'] ?? null;
                $refId = $metadata['ref_id'] ?? $customData['ref_id'] ?? null;
            }

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

    // Legacy methods for backward compatibility
    public function createPaymentSession(string $packageName, User $user)
    {
        $package = Package::where('name', $packageName)->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id,
            'package' => $packageName,
            'amount' => $package->price,
            'status' => 'pending',
            'order_id' => 'ORD-' . strtoupper(Str::random(10)),
            'payment_method' => $user->paymentGateway ? $user->paymentGateway->name : null,
        ]);

        $this->initializeGateway($order);

        switch ($this->gateway) {
            case 'FastSpring':
                /** @var \App\Services\Payment\Gateways\FastSpringPaymentGateway $fastSpringGateway */
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                return $fastSpringGateway->createSession($order);
            case 'Paddle':
                return $this->createPaddleSession($order);
            case 'Pay Pro Global':
                return $this->createPayProGlobalSession($order);
            default:
                throw new \Exception("Unsupported payment gateway: {$this->gateway}");
        }
    }

    protected function createPaddleSession(Order $order)
    {
        $payload = [
            'product_id' => $this->getPaddleProductId($order->package),
            'customer_email' => $order->user->email,
            'passthrough' => json_encode(['order_id' => $order->id]),
            'return_url' => route('payments.success', ['gateway' => 'paddle']),
            'cancel_url' => route('payments.cancel', ['gateway' => 'paddle']),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ])->post($this->config['api_url'] . '/checkout', $payload);

            if ($response->failed()) {
                Log::error('Paddle API Error', ['response' => $response->body()]);
                throw new \Exception('Failed to create Paddle checkout: ' . $response->body());
            }

            return ['success' => true, 'checkoutUrl' => $response->json()['url']];
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function createPayProGlobalSession(Order $order)
    {
        try {
            $payload = [
                'product_id' => $this->getPayProGlobalProductId($order->package),
                'customer' => [
                    'email' => $order->user->email,
                    'first_name' => $order->user->first_name ?? 'Customer',
                    'last_name' => $order->user->last_name ?? $order->user->id,
                ],
                'custom_fields' => [
                    'order_id' => $order->id,
                ],
                'return_url' => route('payments.success', ['gateway' => 'payproglobal']),
                'cancel_url' => route('payments.cancel', ['gateway' => 'payproglobal']),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])->post($this->config['api_url'] . '/checkout-sessions', $payload);

            if ($response->failed()) {
                Log::error('PayProGlobal API Error', ['response' => $response->body()]);
                throw new \Exception('Failed to create PayProGlobal checkout: ' . $response->body());
            }

            return ['success' => true, 'checkoutUrl' => $response->json()['checkout_url']];
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getPaddleProductId(string $package): int
    {
        $mapping = [
            'Starter' => $this->config['product_ids']['starter'],
            'Pro' => $this->config['product_ids']['pro'],
            'Business' => $this->config['product_ids']['business'],
            'Enterprise' => $this->config['product_ids']['enterprise'],
        ];
        return $mapping[$package] ?? $this->config['product_ids']['starter'];
    }

    protected function getPayProGlobalProductId(string $package): int
    {
        $mapping = [
            'Starter' => $this->config['product_ids']['starter'],
            'Pro' => $this->config['product_ids']['pro'],
            'Business' => $this->config['product_ids']['business'],
            'Enterprise' => $this->config['product_ids']['enterprise'],
        ];
        return $mapping[$package] ?? $this->config['product_ids']['starter'];
    }

    public function handlePaymentCallback(array $data)
    {
        switch ($this->gateway) {
            case 'FastSpring':
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                return $fastSpringGateway->processWebhook($data);
            case 'Paddle':
                return $this->handlePaddleCallback($data);
            case 'Pay Pro Global':
                return $this->handlePayProGlobalCallback($data);
            default:
                throw new \Exception("Unsupported payment gateway: {$this->gateway}");
        }
    }

    protected function handlePaddleCallback(array $data)
    {
        $publicKey = $this->config['public_key'];
        $signature = base64_decode($data['p_signature']);
        $fields = $data;
        unset($fields['p_signature']);
        ksort($fields);
        $dataToVerify = serialize($fields);
        $verified = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA1);

        if ($verified !== 1) {
            Log::error('Invalid Paddle webhook signature');
            throw new \Exception('Invalid Paddle webhook signature');
        }

        if ($data['alert_name'] === 'payment_succeeded') {
            $passthrough = json_decode($data['passthrough'], true);
            $orderId = $passthrough['order_id'] ?? null;
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => $data['checkout_id'],
                        'payment_method' => 'Paddle',
                    ]);
                    app(\App\Http\Controllers\SubscriptionController::class)->updateUserSubscription($order);
                }
            }
        }
        return true;
    }

    protected function handlePayProGlobalCallback(array $data)
    {
        $receivedSignature = $data['signature'] ?? '';
        $payload = $data;
        unset($payload['signature']);
        $calculatedSignature = hash_hmac('sha256', json_encode($payload), $this->config['webhook_secret']);
        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            Log::error('Invalid PayProGlobal webhook signature');
            throw new \Exception('Invalid PayProGlobal webhook signature');
        }

        if ($data['event_type'] === 'payment_success') {
            $customFields = $data['custom_fields'] ?? [];
            $orderId = $customFields['order_id'] ?? null;
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'payment_method' => 'Pay Pro Global',
                    ]);
                    app(\App\Http\Controllers\SubscriptionController::class)->updateUserSubscription($order);
                }
            }
        }
        return true;
    }

    // ==================== SUCCESS CALLBACK PROCESSING ====================

    public function processPaddleSuccessCallback(string $transactionId, array $requestData = [])
    {
        $transactionData = $this->verifyPaddleTransaction($transactionId);

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

        // Process as regular payment
        $packageName = $customData['package'] ?? null;
        if (!$packageName) {
            return ['success' => false, 'error' => 'Invalid transaction data'];
        }

        $result = $this->processPaddlePaymentFromWebhook($transactionData, $packageName, $userId);
        return ['success' => $result, 'user_id' => $userId, 'package_name' => $packageName];
    }


    public function processPayProGlobalSuccessCallback(array $requestData)
    {
        $customData = json_decode($requestData['custom'] ?? '{}', true);
        $authToken = $requestData['auth_token'] ?? null;
        $userId = null;

        if ($authToken) {
            $cachedUserId = Cache::get("paypro_auth_token_{$authToken}");
            if ($cachedUserId) {
                $userId = $cachedUserId;
                Cache::forget("paypro_auth_token_{$authToken}");
            }
        }

        $userId = $userId ?? $requestData['user_id'] ?? $customData['user_id'] ?? null;
        $packageSlug = $requestData['package'] ?? $customData['package'] ?? null;
        $pendingOrderId = $requestData['pending_order_id'] ?? $customData['pending_order_id'] ?? null;
        $action = $requestData['action'] ?? $customData['action'] ?? 'new';

        if (!$userId || !$packageSlug || !$pendingOrderId) {
            return ['success' => false, 'error' => 'Invalid payment data from PayProGlobal'];
        }

        $user = User::find($userId);
        $package = Package::where('name', $packageSlug)->first();
        $pendingOrder = Order::where('transaction_id', $pendingOrderId)->first();

        if (!$user || !$package || !$pendingOrder) {
            return ['success' => false, 'error' => 'Payment processing error (data mismatch)'];
        }

        if ($pendingOrder->status === 'completed') {
            return ['success' => true, 'already_completed' => true, 'user' => $user, 'package' => $package];
        }

        return DB::transaction(function () use ($user, $package, $pendingOrder, $requestData, $action) {
            $payProGlobalSubscriptionId = (int)($requestData['ORDER_ITEMS.0.SUBSCRIPTION_ID']
                ?? $requestData['subscriptionId']
                ?? $requestData['transactionId']
                ?? $pendingOrder->transaction_id);

            $payProGlobalOrderId = $requestData['ORDER_ID'] ?? null;
            $finalTransactionId = $payProGlobalSubscriptionId !== 0 ? (string)$payProGlobalSubscriptionId : (string)($payProGlobalOrderId ?? $pendingOrder->transaction_id);

            $isDowngrade = $action === 'downgrade' || $pendingOrder->order_type === 'downgrade';

            // Handle downgrades - always schedule them, never apply immediately
            if ($isDowngrade) {
                $activeLicense = $user->userLicence;
                $scheduledActivationDate = null;

                if ($activeLicense && $activeLicense->expires_at) {
                    $scheduledActivationDate = $activeLicense->expires_at;
                } else {
                    // If no expiry date, schedule for end of current billing cycle (assume 30 days)
                    $scheduledActivationDate = now()->addDays(30);
                }

                // Cancel any existing scheduled downgrades
                Order::where('user_id', $user->id)
                    ->where('order_type', 'downgrade')
                    ->where('status', 'scheduled_downgrade')
                    ->where('id', '!=', $pendingOrder->id)
                    ->update(['status' => 'cancelled']);

                // Update order metadata with scheduled activation date
                $orderMetadata = is_array($pendingOrder->metadata) ? $pendingOrder->metadata : [];
                $orderMetadata['scheduled_activation_date'] = $scheduledActivationDate->toDateTimeString();
                $orderMetadata['original_package_id'] = $user->package_id;
                $orderMetadata['original_package_name'] = $user->package->name ?? 'Unknown';
                $orderMetadata['downgrade_processed'] = false;
                $orderMetadata['subscription_id'] = $payProGlobalSubscriptionId;
                $orderMetadata['payproglobal_order_id'] = $payProGlobalOrderId;

                // Mark order as scheduled_downgrade instead of completed
                $pendingOrder->update([
                    'status' => 'scheduled_downgrade',
                    'transaction_id' => $finalTransactionId,
                    'metadata' => $orderMetadata
                ]);

                // DO NOT update user's package_id or create new license - keep current plan active
                // The downgrade will be processed when the current plan expires

                return [
                    'success' => true,
                    'user' => $user,
                    'package' => $package,
                    'message' => "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.',
                    'action' => 'downgrade',
                    'scheduled_activation_date' => $scheduledActivationDate
                ];
            }

            // For upgrades and new subscriptions, process normally
            $pendingOrder->update([
                'status' => 'completed',
                'completed_at' => now(),
                'transaction_id' => $finalTransactionId,
                'metadata' => array_merge(($pendingOrder->metadata ?? []), [
                    'subscription_id' => $payProGlobalSubscriptionId,
                    'payproglobal_order_id' => $payProGlobalOrderId,
                ]),
            ]);

            $paymentGateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
            if (!$paymentGateway) {
                throw new \Exception('PayProGlobal gateway not configured.');
            }

            $user->update([
                'package_id' => $package->id,
                'is_subscribed' => true,
                'payment_gateway_id' => $paymentGateway->id,
                'subscription_id' => $payProGlobalSubscriptionId
            ]);

            $this->licenseApiService->createAndActivateLicense(
                $user,
                $package,
                $requestData['products.1.id'] ?? null,
                $paymentGateway->id
            );

            return [
                'success' => true,
                'user' => $user,
                'package' => $package,
                'message' => 'Your subscription is now active!',
                'action' => $action
            ];
        });
    }

    protected function processPendingOrder(Order $order, array $transactionData, int $userId)
    {
        $user = User::find($userId);
        $package = Package::find($order->package_id);

        if (!$user || !$package) {
            return ['success' => false, 'error' => 'Order processing failed'];
        }

        $customData = $transactionData['custom_data'] ?? [];
        $action = $customData['action'] ?? $order->order_type ?? 'new';
        $isDowngrade = $action === 'downgrade' || $order->order_type === 'downgrade';

        // Handle downgrades - always schedule them, never apply immediately
        if ($isDowngrade) {
            $activeLicense = $user->userLicence;
            $scheduledActivationDate = null;

            if ($activeLicense && $activeLicense->expires_at) {
                $scheduledActivationDate = $activeLicense->expires_at;
            } else {
                // If no expiry date, schedule for end of current billing cycle (assume 30 days)
                $scheduledActivationDate = now()->addDays(30);
            }

            // Cancel any existing scheduled downgrades
            Order::where('user_id', $user->id)
                ->where('order_type', 'downgrade')
                ->where('status', 'scheduled_downgrade')
                ->where('id', '!=', $order->id)
                ->update(['status' => 'cancelled']);

            // Update order metadata with scheduled activation date
            $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
            $orderMetadata['scheduled_activation_date'] = $scheduledActivationDate->toDateTimeString();
            $orderMetadata['original_package_id'] = $user->package_id;
            $orderMetadata['original_package_name'] = $user->package->name ?? 'Unknown';
            $orderMetadata['downgrade_processed'] = false;

            // Mark order as scheduled_downgrade instead of completed
            $order->update([
                'status' => 'scheduled_downgrade',
                'metadata' => $orderMetadata
            ]);

            // DO NOT update user's package_id or create new license - keep current plan active
            // The downgrade will be processed when the current plan expires

            return [
                'success' => true,
                'user' => $user,
                'package' => $package,
                'is_downgrade' => true,
                'scheduled_activation_date' => $scheduledActivationDate,
                'message' => "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.'
            ];
        }

        // For upgrades and new subscriptions, process normally
        $order->update(['status' => 'completed']);

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
            $isUpgrade = $action === 'upgrade' || $order->order_type === 'upgrade';

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


    // ==================== WEBHOOK PROCESSING ====================

    public function processPaddleWebhook(array $payload)
    {
        $eventType = $payload['event_type'] ?? null;
        $eventData = $payload['data'] ?? [];

        if (!$eventType) {
            return ['error' => 'Invalid webhook payload'];
        }

        switch ($eventType) {
            case 'transaction.completed':
            case 'transaction.paid':
                return $this->handlePaddleTransactionCompleted($eventData);

            case 'subscription.created':
            case 'subscription.updated':
                return $this->handlePaddleSubscriptionEvent($eventData);

            case 'subscription.cancelled':
                return $this->handlePaddleSubscriptionCancelled($eventData);

            default:
                return ['status' => 'ignored'];
        }
    }


    public function processPayProGlobalWebhook(array $payload)
    {
        $orderId = $payload['ORDER_ID'] ?? null;
        $ipnType = $payload['IPN_TYPE_NAME'] ?? null;

        if ($ipnType !== 'OrderCharged' || !$orderId) {
            return ['success' => false, 'message' => 'Event ignored'];
        }

        $checkoutQueryString = $payload['CHECKOUT_QUERY_STRING'] ?? null;
        $userId = null;
        $pendingOrderId = null;
        $packageSlug = null;
        $action = 'new';

        if ($checkoutQueryString) {
            parse_str($checkoutQueryString, $checkoutParams);
            $customDataJson = $checkoutParams['custom'] ?? null;
            if ($customDataJson) {
                $customData = json_decode($customDataJson, true);
                if (!$customData) {
                    $customData = json_decode(urldecode($customDataJson), true);
                }
                if ($customData) {
                    $userId = $customData['user_id'] ?? null;
                    $pendingOrderId = $customData['pending_order_id'] ?? null;
                    $packageSlug = $customData['package'] ?? null;
                    $action = $customData['action'] ?? 'new';
                }
            }
        }

        $user = null;
        if ($userId) {
            $user = User::find($userId);
        }

        if (!$user && isset($payload['CUSTOMER_EMAIL'])) {
            $user = User::where('email', $payload['CUSTOMER_EMAIL'])->first();
        }

        if (!$user && $pendingOrderId) {
            $pendingOrder = Order::where('transaction_id', $pendingOrderId)->first();
            if ($pendingOrder) {
                $user = $pendingOrder->user;
            }
        }

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $package = null;
        if (isset($payload['PRODUCT_ID'])) {
            $productIdsConfig = config('payment.gateways.PayProGlobal.product_ids', []);
            foreach ($productIdsConfig as $slug => $configProductId) {
                if ((string)$configProductId === (string)$payload['PRODUCT_ID']) {
                    $packageName = ucfirst($slug);
                    $package = Package::where('name', $packageName)->first();
                    break;
                }
            }
        }

        if (!$package && $packageSlug) {
            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageSlug)])->first();
            if (!$package) {
                $packageName = ucfirst($packageSlug);
                $package = Package::where('name', $packageName)->first();
            }
        }

        if (!$package) {
            return ['success' => false, 'error' => 'Package not found'];
        }

        $existingOrder = Order::where('transaction_id', $orderId)->first();
        if ($existingOrder && $existingOrder->status === 'completed') {
            return ['success' => true, 'message' => 'Payment already processed'];
        }

        $paymentData = [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'package' => $package->name,
            'amount' => $payload['ORDER_TOTAL_AMOUNT_SHOWN'] ?? $payload['ORDER_ITEM_TOTAL_AMOUNT'] ?? $payload['ORDER_TOTAL_AMOUNT'] ?? null,
            'currency' => $payload['ORDER_CURRENCY_CODE'] ?? null,
            'customer_email' => $payload['CUSTOMER_EMAIL'] ?? null,
            'product_id' => $payload['PRODUCT_ID'] ?? null,
            'action' => $action,
            'pending_order_id' => $pendingOrderId,
            'checkout_query_string' => $checkoutQueryString ?? null
        ];

        $result = $this->processPayment($paymentData, 'payproglobal', false);
        return ['success' => $result, 'order_id' => $orderId];
    }

    protected function handlePaddleTransactionCompleted(array $eventData)
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

        $result = $this->processPaddlePaymentFromWebhook($eventData, $packageName, $userId);
        return ['status' => $result ? 'processed' : 'failed'];
    }

    protected function handlePaddleSubscriptionEvent(array $eventData)
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

    protected function handlePaddleSubscriptionCancelled(array $eventData)
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


    // ==================== UPGRADE/DOWNGRADE ====================

    public function createUpgradeCheckout(User $user, Package $package, string $subscriptionId, string $gateway)
    {
        switch (strtolower($gateway)) {
            case 'paddle':
                return $this->createPaddleUpgradeCheckout($user, $package, $subscriptionId, null);
            case 'fastspring':
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                return $fastSpringGateway->createUpgradeCheckout($user, $package, $subscriptionId);
            case 'payproglobal':
            case 'pay pro global':
                return $this->createPayProGlobalUpgradeCheckout($user, $package, $subscriptionId);
            default:
                throw new \Exception("Unsupported gateway for upgrade: {$gateway}");
        }
    }

    public function createDowngradeCheckout(User $user, Package $package, string $subscriptionId, string $gateway)
    {
        switch (strtolower($gateway)) {
            case 'paddle':
                /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $paddleGateway */
                $paddleGateway = $this->paymentGatewayFactory->create('paddle');
                $product = $paddleGateway->findProductByName($package->name);
                if (!$product) {
                    return null;
                }
                $price = $paddleGateway->findActivePriceForProduct($product['id']);
                if (!$price) {
                    return null;
                }
                return $this->createPaddleDowngradeCheckout($user, $package, $subscriptionId, $price['id']);
            case 'fastspring':
                /** @var \App\Services\Payment\Gateways\FastSpringPaymentGateway $fastSpringGateway */
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                // Use the configured FastSpring product ID for this package, fallback handled in gateway
                $productId = method_exists($package, 'getGatewayProductId')
                    ? $package->getGatewayProductId('FastSpring')
                    : null;
                return $fastSpringGateway->createDowngradeCheckout($user, $package, $subscriptionId, $productId);
            case 'payproglobal':
            case 'pay pro global':
                return $this->createPayProGlobalDowngradeCheckout($user, $package, $subscriptionId);
            default:
                throw new \Exception("Unsupported gateway for downgrade: {$gateway}");
        }
    }


    protected function createPayProGlobalUpgradeCheckout(User $user, Package $package, string $subscriptionId)
    {
        $tempTransactionId = 'PPG-UPGRADE-' . Str::random(10);
        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
            'order_type' => 'upgrade',
            'transaction_id' => $tempTransactionId,
            'metadata' => [
                'original_package' => $user->package->name ?? 'Unknown',
                'upgrade_to' => $package->name,
                'upgrade_type' => 'subscription_upgrade',
                'temp_transaction_id' => true,
                'subscription_id' => $subscriptionId
            ]
        ]);

        $baseUrl = config('payment.gateways.PayProGlobal.base_url', 'https://store.payproglobal.com');
        $merchantId = config('payment.gateways.PayProGlobal.merchant_id', '');
        return "{$baseUrl}/checkout?merchant_id={$merchantId}&product={$package->name}&subscription={$subscriptionId}&order_id={$order->id}&upgrade=true";
    }

    protected function createPayProGlobalDowngradeCheckout(User $user, Package $package, string $subscriptionId)
    {
        $tempTransactionId = 'PPG-DOWNGRADE-' . Str::random(10);
        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
            'order_type' => 'downgrade',
            'subscription_id' => $subscriptionId,
            'transaction_id' => $tempTransactionId,
            'metadata' => [
                'original_package' => $user->package->name ?? 'Unknown',
                'downgrade_to' => $package->name,
                'downgrade_type' => 'subscription_downgrade',
                'temp_transaction_id' => true
            ]
        ]);

        $baseUrl = config('payment.gateways.PayProGlobal.base_url', 'https://store.payproglobal.com');
        $merchantId = config('payment.gateways.PayProGlobal.merchant_id', '');
        return "{$baseUrl}/checkout?merchant_id={$merchantId}&product={$package->name}&subscription={$subscriptionId}&order_id={$order->id}&downgrade=true";
    }

    // ==================== ORDER VERIFICATION ====================

    public function verifyOrder(string $transactionId, int $userId)
    {
        $order = Order::where('transaction_id', $transactionId)
            ->where('user_id', $userId)
            ->first();

        if ($order && $order->status === 'completed') {
            return ['success' => true, 'status' => 'completed', 'message' => 'Order already processed'];
        }

        $transactionData = $this->verifyPaddleTransaction($transactionId);

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

            $result = $this->processPaddlePaymentFromWebhook($transactionData, $packageName, $userId);
            return ['success' => $result, 'status' => 'completed', 'message' => $result ? 'Order processed successfully' : 'Order processing failed'];
        }

        return ['success' => true, 'status' => 'completed', 'message' => 'Order verified successfully'];
    }

    // ==================== SUBSCRIPTION CANCELLATION ====================

    public function cancelSubscription(User $user, string $gateway, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null)
    {
        switch (strtolower($gateway)) {
            case 'fastspring':
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                return $fastSpringGateway->cancelSubscription($user, $subscriptionId, $cancellationReasonId, $reasonText);
            case 'paddle':
                return $this->cancelPaddleSubscription($user, $subscriptionId);
            case 'payproglobal':
            case 'pay pro global':
                return $this->cancelPayProGlobalSubscription($user, $subscriptionId, $cancellationReasonId, $reasonText);
            default:
                throw new \Exception("Unsupported gateway for cancellation: {$gateway}");
        }
    }


    protected function cancelPaddleSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null)
    {
        $paddleGateway = $this->paymentGatewayFactory->create('paddle');
        return $paddleGateway->cancelSubscription($user, $subscriptionId, $cancellationReasonId, $reasonText);
    }

    protected function cancelPayProGlobalSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null)
    {
        // PayProGlobal cancellation is handled by SubscriptionService
        // This method is a placeholder for consistency
        return ['success' => true, 'message' => 'Cancellation delegated to SubscriptionService'];
    }

    // ==================== GATEWAY DETECTION ====================

    public function detectGatewayFromUser(User $user, ?string $subscriptionId = null): ?PaymentGateways
    {
        // First try user's payment gateway
        if ($user->payment_gateway_id) {
            $gateway = $user->paymentGateway;
            if ($gateway) {
                return $gateway;
            }
        }

        // Try to detect from subscription ID
        $subscriptionId = $subscriptionId ?? $user->subscription_id ?? ($user->userLicence->subscription_id ?? null);

        if ($subscriptionId) {
            if (str_starts_with($subscriptionId, 'PADDLE-') || str_starts_with($subscriptionId, 'sub_')) {
                return PaymentGateways::where('name', 'Paddle')->first();
            } elseif (str_starts_with($subscriptionId, 'FS-') || str_starts_with($subscriptionId, 'fastspring_')) {
                return PaymentGateways::where('name', 'FastSpring')->first();
            } elseif (str_starts_with($subscriptionId, 'PPG-') || str_starts_with($subscriptionId, 'payproglobal_')) {
                return PaymentGateways::where('name', 'Pay Pro Global')->first();
            }
        }

        // Try Paddle customer ID
        if ($user->paddle_customer_id) {
            return PaymentGateways::where('name', 'Paddle')->first();
        }

        return null;
    }

    // ==================== PADDLE UPGRADE WITH PRICE ID ====================

    public function createPaddleUpgradeCheckoutWithPriceId(User $user, Package $package, string $subscriptionId)
    {
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $productsResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

        if (!$productsResponse->successful()) {
            throw new \Exception('Failed to retrieve Paddle products');
        }

        $products = $productsResponse->json()['data'];
        $matchingProduct = collect($products)->firstWhere('name', $package->name);

        if (!$matchingProduct) {
            throw new \Exception('Paddle product not found');
        }

        $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
        if (!$price) {
            throw new \Exception('No active price found');
        }

        return $this->createPaddleUpgradeCheckout($user, $package, $subscriptionId, $price['id']);
    }

    // ==================== PAYPROGLOBAL SCHEDULED DOWNGRADE ====================

    public function createPayProGlobalScheduledDowngrade(User $user, Package $targetPackage, Package $currentPackage)
    {
        $activeLicense = $user->userLicence;
        if (!$activeLicense || !$activeLicense->expires_at) {
            throw new \Exception('No active license found');
        }

        $pendingOrderId = 'PPG-DOWNGRADE-' . uniqid();
        $pendingOrder = Order::create([
            'user_id' => $user->id,
            'package_id' => $targetPackage->id,
            'order_type' => 'downgrade',
            'status' => 'scheduled_downgrade',
            'transaction_id' => $pendingOrderId,
            'amount' => $targetPackage->price,
            'currency' => 'USD',
            'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
            'metadata' => [
                'original_package_id' => $currentPackage->id,
                'original_package_name' => $currentPackage->name,
                'scheduled_activation_date' => $activeLicense->expires_at->toDateTimeString(),
                'downgrade_processed' => false,
            ]
        ]);

        $redirectUrl = route('payments.success', [
            'gateway' => 'payproglobal',
            'user_id' => $user->id,
            'package' => $targetPackage->name,
            'popup' => 'true',
            'pending_order_id' => $pendingOrder->transaction_id,
            'action' => 'downgrade'
        ]);

        return [
            'success' => true,
            'message' => 'Your downgrade has been successfully scheduled.',
            'redirect_url' => $redirectUrl,
            'order_id' => $pendingOrder->id
        ];
    }

    // ==================== ADDON PROCESSING ====================

    // ==================== SUBSCRIPTION CANCELLATION ====================

    public function cancelSubscriptionWithoutExternalId(User $user)
    {
        return DB::transaction(function () use ($user) {
            $userLicense = $user->userLicence;
            if ($userLicense) {
                $userLicense->delete();
            }

            $user->update([
                'is_subscribed' => false,
                'package_id' => null,
                'payment_gateway_id' => null,
                'subscription_id' => null,
                'user_license_id' => null
            ]);

            $order = Order::where('user_id', $user->id)
                ->latest('created_at')
                ->first();

            if ($order) {
                $order->update(['status' => 'canceled']);
            }

            return ['success' => true, 'message' => 'Subscription cancelled successfully'];
        });
    }

    // ==================== PACKAGE VALIDATION ====================

    public function validatePackageAndGetUser(string $packageName): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $package = ucfirst($packageName);
        $packageData = Package::where('name', $package)->first();

        if (!$packageData) {
            throw new \Exception('Package not found');
        }

        return ['user' => $user, 'packageData' => $packageData];
    }

    public function processPackageName(string $package): string
    {
        return str_replace('-plan', '', strtolower($package));
    }

    public function isPrivilegedUser(?User $user): bool
    {
        try {
            if (!$user) {
                return false;
            }
            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole(['Super Admin', 'Sub Admin']);
            }
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('Super Admin') || $user->hasRole('Sub Admin');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }

    // ==================== LICENSE AVAILABILITY ====================

    public function checkLicenseAvailability(User $user, string $plan = 'Free'): bool
    {
        $tenantId = $user->tenant_id;

        if (!$tenantId) {
            Log::error('Tenant_id not found', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            return false;
        }

        $resolved = $this->licenseApiService->resolvePlanLicense($tenantId, $plan, false);
        if (!$resolved) {
            Log::error('Requested plan not found in API inventory', [
                'plan' => $plan,
                'tenant_id' => $tenantId,
            ]);
            return false;
        }

        $remaining = (int)($resolved['remaining'] ?? 0);
        if ($remaining <= 0) {
            Log::error('No remaining licenses available for requested plan', [
                'plan' => $plan,
                'tenant_id' => $tenantId,
                'resolved' => $resolved,
            ]);
            return false;
        }

        Log::info('License availability check passed for plan', [
            'plan' => $plan,
            'subscription_name' => $resolved['subscriptionName'] ?? null,
            'subscription_code' => $resolved['subscriptionCode'] ?? null,
            'remaining' => $remaining,
        ]);
        return true;
    }

    public function retryTenantAndPasswordBinding(User $user): ?bool
    {
        if (!method_exists($user, 'hasRole') || !$user->hasRole('User')) {
            return null;
        }

        Log::info('License unavailable - attempting tenant and password retry', [
            'user_id' => $user->id,
            'email' => $user->email,
            'has_tenant_id' => !empty($user->tenant_id),
            'has_subscriber_password' => !empty($user->subscriber_password)
        ]);

        $plainPassword = $user->subscriber_password;

        if (!$plainPassword) {
            Log::warning('Cannot retry tenant/password binding - missing subscriber_password', [
                'user_id' => $user->id
            ]);
            return false;
        }

        $tenantResult = $this->tenantAssignmentService->assignTenantWithRetry($user, $plainPassword);

        if ($tenantResult['success'] && !empty($tenantResult['data']['tenantId'])) {
            $user = User::find($user->id);

            $passwordResult = $this->passwordBindingService->bindPasswordWithRetry($user, $plainPassword);

            if ($passwordResult['success']) {
                Log::info('Tenant and password binding successful after retry', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id
                ]);
                return true;
            } else {
                Log::warning('Tenant assigned but password binding failed after retry', [
                    'user_id' => $user->id,
                    'error' => $passwordResult['error_message'] ?? 'Unknown error'
                ]);
            }
        } else {
            Log::warning('Tenant assignment failed after retry', [
                'user_id' => $user->id,
                'error' => $tenantResult['error_message'] ?? 'Unknown error'
            ]);
        }

        return false;
    }

    // ==================== CHECKOUT CREATION WITH VALIDATION ====================

    public function createCheckoutWithValidation(string $packageName, string $gateway, array $options = []): array
    {
        $processedPackage = $this->processPackageName($packageName);
        $validation = $this->validatePackageAndGetUser($processedPackage);

        $user = $validation['user'];
        $packageData = $validation['packageData'];

        if ($packageData->isFree()) {
            return $this->assignFreePlanImmediately($user, $packageData);
        }

        if (!$this->checkLicenseAvailability($user, $packageData->name)) {
            $retrySuccess = $this->retryTenantAndPasswordBinding($user);
            if ($retrySuccess === true) {
                $user = User::find($user->id);
                if (!$this->checkLicenseAvailability($user, $packageData->name)) {
                    throw new \Exception('Licenses temporarily unavailable');
                }
            } else {
                throw new \Exception('Licenses temporarily unavailable');
            }
        }

        if (!$this->isPrivilegedUser($user) && !$this->licenseApiService->canUserChangePlan($user)) {
            throw new \Exception('Plan change restricted');
        }

        $gatewayInstance = $this->paymentGatewayFactory->create($gateway);
        return $gatewayInstance->createCheckout($user, $packageData, $options);
    }

    public function createUpgradeCheckoutWithValidation(string $packageName, string $gateway, array $options = []): array
    {
        $processedPackage = $this->processPackageName($packageName);
        $validation = $this->validatePackageAndGetUser($processedPackage);

        $user = $validation['user'];
        $packageData = $validation['packageData'];

        $currentLicense = $user->userLicence;
        if (!$currentLicense || !$currentLicense->subscription_id) {
            throw new \Exception('License configuration issue');
        }

        if (!$this->checkLicenseAvailability($user, $packageData->name)) {
            $retrySuccess = $this->retryTenantAndPasswordBinding($user);
            if ($retrySuccess === true) {
                $user = User::find($user->id);
                if (!$this->checkLicenseAvailability($user, $packageData->name)) {
                    throw new \Exception('Licenses temporarily unavailable');
                }
            } else {
                throw new \Exception('Licenses temporarily unavailable');
            }
        }

        $gatewayInstance = $this->paymentGatewayFactory->create($gateway);
        $checkoutUrl = $gatewayInstance->createUpgradeCheckout($user, $packageData, $currentLicense->subscription_id);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'message' => 'Upgrade checkout created successfully'
        ];
    }

    public function assignFreePlanImmediately(User $user, Package $package): array
    {
        $result = $this->subscriptionService->assignFreePlanImmediately($user, $package);
        return [
            'success' => true,
            'message' => 'Free plan activated successfully',
            'order_id' => $result['order_id']
        ];
    }

    // ==================== SUCCESS CALLBACK PROCESSING ====================

    public function processSuccessCallback(string $gateway, array $requestData): array
    {
        try {
            switch (strtolower($gateway)) {
                case 'paddle':
                    return $this->processPaddleSuccessCallback(
                        $requestData['transaction_id'] ?? $requestData['transactionId'] ?? null,
                        $requestData
                    );
                case 'fastspring':
                    $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                    return $fastSpringGateway->processSuccessCallback($requestData);
                case 'payproglobal':
                    return $this->processPayProGlobalSuccessCallback($requestData);
                default:
                    return ['success' => false, 'error' => "Unsupported gateway: {$gateway}"];
            }
        } catch (\Exception $e) {
            Log::error('Payment success callback exception', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => 'Payment processing failed: ' . $e->getMessage()];
        }
    }

    // ==================== WEBHOOK PROCESSING ====================

    public function processWebhook(string $gateway, array $payload): array
    {
        switch (strtolower($gateway)) {
            case 'paddle':
                return $this->processPaddleWebhook($payload);
            case 'fastspring':
                $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
                return $fastSpringGateway->processWebhook($payload);
            case 'payproglobal':
                return $this->processPayProGlobalWebhook($payload);
            default:
                throw new \Exception("Unsupported gateway: {$gateway}");
        }
    }

    // ==================== SUBSCRIPTION MANAGEMENT ====================

    public function handleSubscriptionCancellation(User $user, ?int $cancellationReasonId = null, ?string $reasonText = null): array
    {
        if (!$user->is_subscribed) {
            throw new \Exception('No active subscription found');
        }

        $hasScheduledChange = Order::where('user_id', $user->id)
            ->whereIn('status', ['scheduled_downgrade', 'cancellation_scheduled'])
            ->exists();

        if ($hasScheduledChange) {
            throw new \Exception('Plan change restricted: Scheduled change already exists');
        }

        $userLicense = $user->userLicence;
        if (!$userLicense || !$userLicense->subscription_id) {
            return $this->cancelSubscriptionWithoutExternalId($user);
        }

        $subscriptionId = $userLicense->subscription_id;
        $gateway = $user->paymentGateway ? $user->paymentGateway->name : null;

        if (!$gateway) {
            throw new \Exception('No payment gateway found');
        }

        $gatewayInstance = $this->paymentGatewayFactory->create($gateway);
        return $gatewayInstance->cancelSubscription($user, $subscriptionId, $cancellationReasonId, $reasonText);
    }

    public function handleUpgradeToPackage(User $user, string $packageName, string $gateway): array
    {
        if (!$user->is_subscribed) {
            throw new \Exception('Subscription required');
        }

        $hasScheduledChange = Order::where('user_id', $user->id)
            ->whereIn('status', ['scheduled_downgrade', 'cancellation_scheduled'])
            ->exists();

        if ($hasScheduledChange) {
            throw new \Exception('Plan change restricted: Scheduled change already exists');
        }

        $currentLicense = $user->userLicence;
        if (!$currentLicense || !$currentLicense->subscription_id) {
            throw new \Exception('License configuration issue');
        }

        $subscriptionId = $currentLicense->subscription_id;
        $processedPackage = $this->processPackageName($packageName);
        $packageData = Package::where('name', ucfirst($processedPackage))->first();

        if (!$packageData) {
            throw new \Exception('Invalid package');
        }

        if ($gateway === 'Paddle' && !$user->paddle_customer_id) {
            /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $paddleGateway */
            $paddleGateway = $this->paymentGatewayFactory->create('paddle');
            $paddleCustomerId = $paddleGateway->ensurePaddleCustomerId($user);
            if (!$paddleCustomerId) {
                throw new \Exception('Paddle customer ID missing');
            }
        }

        $gatewayInstance = $this->paymentGatewayFactory->create($gateway);
        $checkoutUrl = $gatewayInstance->createUpgradeCheckout($user, $packageData, $subscriptionId);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'message' => 'Upgrade order created successfully'
        ];
    }

    public function handleDowngradeSubscription(User $user, string $packageName, string $gateway): array
    {
        if (!$user->is_subscribed) {
            throw new \Exception('Subscription required');
        }

        $hasScheduledChange = Order::where('user_id', $user->id)
            ->whereIn('status', ['scheduled_downgrade', 'cancellation_scheduled'])
            ->exists();

        if ($hasScheduledChange) {
            throw new \Exception('Plan change restricted: Scheduled change already exists');
        }

        Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'pending_downgrade'])
            ->update(['status' => 'cancelled']);

        // Check if user can change plan (excluding pending downgrades since we just cancelled them)
        // We need to check other restrictions like upgrade licenses
        $activeLicense = $user->userLicence;
        if ($activeLicense && $activeLicense->is_upgrade_license && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            throw new \Exception('Plan change restricted: Upgrade license is still active');
        }

        $processedPackage = $this->processPackageName($packageName);
        $packageData = Package::where('name', ucfirst($processedPackage))->first();

        if (!$packageData) {
            throw new \Exception('Invalid package');
        }

        $currentLicense = $user->userLicence;
        if (!$currentLicense || !$currentLicense->subscription_id) {
            throw new \Exception('License configuration issue');
        }

        $subscriptionId = $currentLicense->subscription_id;

        if ($gateway === 'Paddle' && !$user->paddle_customer_id) {
            /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $paddleGateway */
            $paddleGateway = $this->paymentGatewayFactory->create('paddle');
            $paddleCustomerId = $paddleGateway->ensurePaddleCustomerId($user);
            if (!$paddleCustomerId) {
                throw new \Exception('Paddle customer ID missing');
            }
        }

        $gatewayInstance = $this->paymentGatewayFactory->create($gateway);
        $checkoutUrl = $gatewayInstance->createDowngradeCheckout($user, $packageData, $subscriptionId);

        if (!$checkoutUrl) {
            throw new \Exception('Failed to create downgrade checkout');
        }

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'message' => 'Downgrade checkout created successfully'
        ];
    }

    public function handlePayProGlobalDowngrade(User $user, int $targetPackageId): array
    {
        $targetPackage = Package::find($targetPackageId);
        $currentPackage = $user->package;

        if (!$targetPackage || !$currentPackage) {
            throw new \Exception('Invalid package selection');
        }

        if ($targetPackage->price >= $currentPackage->price) {
            throw new \Exception('Selected package is not a downgrade');
        }

        /** @var \App\Services\Payment\Gateways\PayProGlobalPaymentGateway $payProGlobalGateway */
        $payProGlobalGateway = $this->paymentGatewayFactory->create('payproglobal');
        return $payProGlobalGateway->createScheduledDowngrade($user, $targetPackage, $currentPackage);
    }

    // ==================== ORDER VERIFICATION ====================

    public function verifyOrderForUser(string $transactionId, int $userId): array
    {
        /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $paddleGateway */
        $paddleGateway = $this->paymentGatewayFactory->create('paddle');
        return $paddleGateway->verifyOrder($transactionId, $userId);
    }

    // ==================== ADDON PROCESSING ====================

    public function processAddonSuccess(User $user, string $orderId, string $addon): array
    {
        /** @var \App\Services\Payment\Gateways\FastSpringPaymentGateway $fastSpringGateway */
        $fastSpringGateway = $this->paymentGatewayFactory->create('fastspring');
        $result = $fastSpringGateway->processAddonOrder($user, $orderId, $addon);
        return ['success' => true, 'message' => $result['message'] ?? 'Add-on purchase successful'];
    }

    // ==================== PADDLE CONFIGURATION TEST ====================

    public function testPaddleConfiguration(): array
    {
        $apiKey = config('payment.gateways.Paddle.api_key');
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $config = [
            'api_key_exists' => !empty($apiKey),
            'api_key_length' => strlen($apiKey ?? ''),
            'environment' => $environment,
            'api_base_url' => $apiBaseUrl,
            'api_url_config' => config('payment.gateways.Paddle.api_url')
        ];

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Paddle API key not configured',
                'config' => $config
            ];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->get("{$apiBaseUrl}/products");

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'config' => $config,
            'products_count' => $response->successful() ? count($response->json()['data'] ?? []) : 0,
            'products' => $response->successful() ? collect($response->json()['data'] ?? [])->pluck('name')->toArray() : []
        ];
    }
}
