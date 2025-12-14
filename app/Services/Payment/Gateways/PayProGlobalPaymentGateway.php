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
use Illuminate\Support\Facades\{
    Http,
    Log,
    DB,
    Cache
};
use Illuminate\Support\Str;
use Carbon\Carbon;

class PayProGlobalPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected LicenseApiService $licenseApiService,
        protected FirstPromoterService $firstPromoterService
    ) {}

    public function getName(): string
    {
        return 'Pay Pro Global';
    }

    protected function getPaymentGatewayId(): ?int
    {
        return PaymentGateways::where('name', 'Pay Pro Global')->value('id');
    }

    public function createCheckout(User $user, Package $package, array $options = []): array
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
            'payment_gateway_id' => $this->getPaymentGatewayId(),
            'status' => 'pending',
            'metadata' => $orderMetadata
        ]);

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

    public function processPayment(array $paymentData, bool $returnRedirect = true)
    {
        return DB::transaction(function () use ($paymentData, $returnRedirect) {
            $userId = $paymentData['user_id'] ?? null;
            $packageName = ucfirst($paymentData['package']) ?? null;
            $transactionId = $paymentData['order_id'] ?? null;
            $amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : null;
            $subscriptionId = $paymentData['subscription_id'] ?? null;
            $action = $paymentData['action'] ?? 'new';
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
                    'amount' => $amount ?? $package->price,
                    'currency' => $currency,
                    'payment_gateway_id' => $this->getPaymentGatewayId(),
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
                    $scheduledActivationDate = now()->addDays(30);
                }

                Order::where('user_id', $user->id)
                    ->where('order_type', 'downgrade')
                    ->where('status', 'scheduled_downgrade')
                    ->update(['status' => 'cancelled']);

                $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
                $orderMetadata['scheduled_activation_date'] = $scheduledActivationDate->toDateTimeString();
                $orderMetadata['original_package_id'] = $user->package_id;
                $orderMetadata['original_package_name'] = $user->package->name ?? 'Unknown';
                $orderMetadata['downgrade_processed'] = false;

                $order->update([
                    'status' => 'scheduled_downgrade',
                    'metadata' => $orderMetadata
                ]);

                return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.'] : true;
            }

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

        $result = $this->processPayment($paymentData, false);
        return ['success' => $result, 'order_id' => $orderId];
    }

    public function processSuccessCallback(array $requestData): array
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

        return DB::transaction(function () use ($user, $package, $pendingOrder, $requestData) {
            $payProGlobalSubscriptionId = (int)($requestData['ORDER_ITEMS.0.SUBSCRIPTION_ID']
                ?? $requestData['subscriptionId']
                ?? $requestData['transactionId']
                ?? $pendingOrder->transaction_id);

            $payProGlobalOrderId = $requestData['ORDER_ID'] ?? null;
            $finalTransactionId = $payProGlobalSubscriptionId !== 0 ? (string)$payProGlobalSubscriptionId : (string)($payProGlobalOrderId ?? $pendingOrder->transaction_id);

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

            $action = $requestData['action'] ?? 'new';
            $successMessage = 'Your subscription is now active!';
            if ($action === 'downgrade') {
                $scheduledActivationDate = null;
                if (is_array($pendingOrder->metadata) && isset($pendingOrder->metadata['scheduled_activation_date'])) {
                    $scheduledActivationDate = Carbon::parse($pendingOrder->metadata['scheduled_activation_date']);
                }
                if ($scheduledActivationDate) {
                    $successMessage = "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.';
                } else {
                    $successMessage = "Downgrade to {$package->name} scheduled successfully. It will activate at the end of your current billing cycle.";
                }
            }

            return [
                'success' => true,
                'user' => $user,
                'package' => $package,
                'message' => $successMessage,
                'action' => $action
            ];
        });
    }

    public function createUpgradeCheckout(User $user, Package $package, string $subscriptionId): string
    {
        $tempTransactionId = 'PPG-UPGRADE-' . Str::random(10);
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
                'temp_transaction_id' => true,
                'subscription_id' => $subscriptionId
            ]
        ]);

        $baseUrl = config('payment.gateways.PayProGlobal.base_url', 'https://store.payproglobal.com');
        $merchantId = config('payment.gateways.PayProGlobal.merchant_id', '');
        return "{$baseUrl}/checkout?merchant_id={$merchantId}&product={$package->name}&subscription={$subscriptionId}&order_id={$order->id}&upgrade=true";
    }

    public function createDowngradeCheckout(User $user, Package $package, string $subscriptionId): string
    {
        $tempTransactionId = 'PPG-DOWNGRADE-' . Str::random(10);
        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => $this->getPaymentGatewayId(),
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

    public function cancelSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null): array
    {
        $response = $this->cancelPayProGlobalSubscription($subscriptionId, $cancellationReasonId, $reasonText);

        if ($response && isset($response['success']) && $response['success']) {
            $order = Order::where('user_id', $user->id)->latest('created_at')->first();
            if ($order) {
                $order->update(['status' => 'cancellation_scheduled']);
            }
            return ['success' => true, 'message' => 'Cancellation scheduled'];
        }

        return ['success' => true, 'message' => 'Cancellation delegated to SubscriptionService'];
    }

    protected function getPayProGlobalCredentials(): array
    {
        return [
            'api_key' => config('payment.gateways.PayProGlobal.api_key'),
            'vendor_id' => config('payment.gateways.PayProGlobal.vendor_account_id'),
            'api_secret_key' => config('payment.gateways.PayProGlobal.api_secret_key')
        ];
    }

    public function upgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null)
    {
        $credentials = $this->getPayProGlobalCredentials();
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credentials['api_key'],
            'Content-Type' => 'application/json'
        ])->post('https://api.payproglobal.com/v1/subscriptions/upgrade', [
            'subscription_id' => $subscriptionId,
            'product_id' => $newProductId,
            'prorate' => true
        ]);

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null)
    {
        $credentials = $this->getPayProGlobalCredentials();
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credentials['api_key'],
            'Content-Type' => 'application/json'
        ])->post('https://api.payproglobal.com/v1/subscriptions/downgrade', [
            'subscription_id' => $subscriptionId,
            'product_id' => $newProductId
        ]);

        return $response->json();
    }

    public function downgradeSubscriptionForUser(User $user, string $subscriptionId, string $newProductId)
    {
        return $this->downgradeSubscription($subscriptionId, $newProductId);
    }

    protected function cancelPayProGlobalSubscription(string $subscriptionId, int $cancellationReasonId = null, string $reasonText = null, bool $sendCustomerNotification = false)
    {
        $credentials = $this->getPayProGlobalCredentials();
        $payload = [
            'subscriptionId' => (int) $subscriptionId,
            'vendorAccountId' => (int) $credentials['vendor_id'],
            'apiSecretKey' => $credentials['api_secret_key'],
            'sendCustomerNotification' => $sendCustomerNotification
        ];

        if ($cancellationReasonId) {
            $payload['cancellationReasonId'] = $cancellationReasonId;
        } elseif ($reasonText) {
            $payload['reasonText'] = $reasonText;
        } else {
            $payload['cancellationReasonId'] = 2;
        }

        $response = Http::post('https://store.payproglobal.com/api/Subscriptions/Terminate', $payload);

        return $response->json();
    }

    public function verifyTransaction(string $transactionId): ?array
    {
        return null;
    }

    public function createScheduledDowngrade(User $user, Package $targetPackage, Package $currentPackage): array
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
            'payment_gateway_id' => $this->getPaymentGatewayId(),
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
}
