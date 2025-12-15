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
use Carbon\Carbon;

class FastSpringPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected LicenseApiService $licenseApiService,
        protected FirstPromoterService $firstPromoterService
    ) {}

    protected function getFastSpringCredentials(): array
    {
        return [
            'username' => config('payment.gateways.FastSpring.username'),
            'password' => config('payment.gateways.FastSpring.password')
        ];
    }

    protected function fastspringClient()
    {
        $credentials = $this->getFastSpringCredentials();

        return Http::withBasicAuth($credentials['username'], $credentials['password']);
    }

    public function getName(): string
    {
        return 'FastSpring';
    }

    protected function getPaymentGatewayId(): ?int
    {
        return PaymentGateways::where('name', 'FastSpring')->value('id');
    }

    public function createCheckout(User $user, Package $package, array $options = []): array
    {
        $isUpgrade = $options['is_upgrade'] ?? false;
        $processedPackage = strtolower(str_replace('-plan', '', $package->name));

        $storefront = config('payment.gateways.FastSpring.storefront');
        if (!$storefront) {
            throw new \Exception('FastSpring storefront not configured');
        }

        $secureHash = hash_hmac(
            'sha256',
            $user->id . $processedPackage . time(),
            config('payment.gateways.FastSpring.webhook_secret')
        );

        $queryParams = [
            'referrer' => $user->id,
            'contactEmail' => $user->email,
            'contactFirstName' => $user->first_name ?? '',
            'contactLastName' => $user->last_name ?? '',
            'tags' => json_encode([
                'user_id' => $user->id,
                'package' => $package->name,
                'package_id' => $package->id,
                'secure_hash' => $secureHash,
                'action' => $isUpgrade ? 'upgrade' : 'new'
            ]),
            'mode' => 'popup',
            'successUrl' => route('payments.success', [
                'gateway' => 'fastspring',
                'orderId' => '{orderReference}',
                'popup' => 'true',
                'package_name' => $processedPackage,
                'payment_gateway_id' => $this->getPaymentGatewayId()
            ]),
            'cancelUrl' => route('payments.popup-cancel')
        ];

        if ($isUpgrade && $user->subscription_id) {
            $queryParams['subscription_id'] = $user->subscription_id;
        }

        $checkoutUrl = "https://{$storefront}/{$processedPackage}?" . http_build_query($queryParams);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'transaction_id' => 'FS-PENDING-' . Str::random(10),
            'payment_gateway_id' => $this->getPaymentGatewayId(),
            'status' => 'pending',
            'metadata' => ['checkout_url' => $checkoutUrl]
        ]);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl
        ];
    }

    public function upgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null)
    {
        $response = $this->fastspringClient()
            ->post('https://api.fastspring.com/subscriptions', [
                'subscriptions' => [
                    [
                        'subscription' => $subscriptionId,
                        'product' => $newProductId,
                        'prorate' => true,
                        'preview' => false
                    ]
                ]
            ]);

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null)
    {
        throw new \Exception('FastSpring downgradeSubscription requires a User object. Use downgradeSubscriptionForUser instead.');
    }

    public function downgradeSubscriptionForUser(User $user, string $subscriptionId, string $newProductId)
    {
        try {
            // Find package by FastSpring product ID or name
            $package = Package::where('name', $newProductId)->first();
            if (!$package) {
                // Find by matching product path
                $packageName = ucfirst(str_replace(['-plan', '-'], ['', ' '], $newProductId));
                $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
            }

            if (!$package) {
                throw new \Exception("Package not found for product: {$newProductId}");
            }

            $result = $this->createDowngradeCheckout($user, $package, $subscriptionId, $newProductId);

            // Get the scheduled order to return details
            $order = Order::where('user_id', $user->id)
                ->where('subscription_id', $subscriptionId)
                ->where('order_type', 'downgrade')
                ->where('status', 'scheduled_downgrade')
                ->latest()
                ->first();

            if ($order) {
                $metadata = is_array($order->metadata) ? $order->metadata : [];
                $scheduledDate = isset($metadata['scheduled_activation_date'])
                    ? \Carbon\Carbon::parse($metadata['scheduled_activation_date'])
                    : null;

                return [
                    'success' => true,
                    'scheduled_change' => true,
                    'scheduled_date' => $scheduledDate?->toDateTimeString(),
                    'message' => "Downgrade to {$package->name} scheduled successfully. It will activate on " . ($scheduledDate?->format('M d, Y') ?? 'your next billing date') . '.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to schedule downgrade'
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to prepare FastSpring downgrade', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'new_product' => $newProductId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to prepare downgrade: ' . $e->getMessage()
            ];
        }
    }

    public function cancelSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null): array
    {
        $response = $this->cancelFastSpringSubscription($subscriptionId, 1);
        $responseData = $response->json();

        $isSuccess = false;
        if (isset($responseData['subscriptions']) && is_array($responseData['subscriptions'])) {
            foreach ($responseData['subscriptions'] as $subscription) {
                if (isset($subscription['result']) && $subscription['result'] === 'success') {
                    $isSuccess = true;
                    break;
                }
            }
        }

        if ($isSuccess) {
            $order = Order::where('user_id', $user->id)->latest('created_at')->first();
            if ($order) {
                $order->update(['status' => 'cancellation_scheduled']);
            }
        }

        return ['success' => $isSuccess, 'message' => $isSuccess ? 'Cancellation scheduled' : 'Cancellation failed'];
    }

    public function processPayment(array $paymentData, bool $returnRedirect = true)
    {
        return DB::transaction(function () use ($paymentData, $returnRedirect) {
            $userId = $paymentData['user_id'] ?? null;
            $packageName = ucfirst($paymentData['package']) ?? null;
            $transactionId = $paymentData['order_id'] ?? $paymentData['order'] ?? null;
            $amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : ($paymentData['total'] ?? 0);
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
                    'amount' => $amount,
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

            return $returnRedirect ? ['redirect' => route('user.subscription.details'), 'message' => "Subscription to {$package->name} bought successfully!"] : true;
        });
    }

    public function processWebhook(array $payload): array
    {
        $eventType = $payload['type'] ?? null;
        $subscriptionId = $payload['subscription'] ?? null;

        // Handle subscription.updated - confirms scheduled downgrade
        if ($eventType === 'subscription.updated' && $subscriptionId) {
            return $this->handleSubscriptionUpdated($payload, $subscriptionId);
        }

        // Handle subscription.charge.completed - apply scheduled downgrade when current plan expires
        if ($eventType === 'subscription.charge.completed' && $subscriptionId) {
            return $this->handleSubscriptionChargeCompleted($payload, $subscriptionId);
        }

        if (in_array($eventType, ['order.completed', 'subscription.activated'])) {
            $tags = is_string($payload['tags'] ?? '') ? json_decode($payload['tags'], true) : ($payload['tags'] ?? []);

            if ($subscriptionId) {
                $order = Order::where('metadata->subscription_id', $subscriptionId)
                    ->where('status', 'pending_upgrade')
                    ->latest()
                    ->first();

                if ($order) {
                    $order->update(['status' => 'completed']);
                    $user = $order->user;
                    if ($user) {
                        $user->update([
                            'package_id' => $order->package_id,
                            'is_subscribed' => true,
                            'payment_gateway_id' => $order->payment_gateway_id,
                            'subscription_id' => $subscriptionId
                        ]);

                        $license = $user->userLicence;
                        if ($license) {
                            $license->update([
                                'package_id' => $order->package_id,
                                'updated_at' => now()
                            ]);
                        }
                    }
                    return ['status' => 'processed'];
                }
            }

            $paymentData = array_merge($payload, [
                'user_id' => $tags['user_id'] ?? null,
                'package' => $tags['package'] ?? null,
                'subscription_id' => $subscriptionId,
                'action' => $tags['action'] ?? 'new'
            ]);

            $result = $this->processPayment($paymentData, false);
            return ['status' => $result ? 'processed' : 'failed'];
        } elseif (in_array($eventType, ['subscription.cancelled', 'subscription.deactivated'])) {
            return $this->handleCancellation($payload);
        }

        return ['status' => 'ignored'];
    }

    protected function handleSubscriptionUpdated(array $payload, string $subscriptionId): array
    {
        // Find scheduled downgrade order
        $scheduledDowngrade = Order::where('subscription_id', $subscriptionId)
            ->where('order_type', 'downgrade')
            ->where('status', 'scheduled_downgrade')
            ->latest()
            ->first();

        if ($scheduledDowngrade) {
            Log::info('FastSpring subscription.updated webhook received for scheduled downgrade', [
                'subscription_id' => $subscriptionId,
                'order_id' => $scheduledDowngrade->id,
                'user_id' => $scheduledDowngrade->user_id
            ]);

            // Update metadata to confirm FastSpring has scheduled it
            $metadata = is_array($scheduledDowngrade->metadata) ? $scheduledDowngrade->metadata : [];
            $metadata['fastspring_confirmed'] = true;
            $metadata['updated_at'] = now()->toDateTimeString();
            $scheduledDowngrade->update(['metadata' => $metadata]);

            return ['status' => 'processed', 'message' => 'Scheduled downgrade confirmed'];
        }

        return ['status' => 'ignored'];
    }

    protected function handleSubscriptionChargeCompleted(array $payload, string $subscriptionId): array
    {
        // Find scheduled downgrade that should now be applied
        $scheduledDowngrade = Order::where('subscription_id', $subscriptionId)
            ->where('order_type', 'downgrade')
            ->where('status', 'scheduled_downgrade')
            ->where('metadata->downgrade_processed', false)
            ->latest()
            ->first();

        if (!$scheduledDowngrade) {
            return ['status' => 'ignored'];
        }

        $user = $scheduledDowngrade->user;
        if (!$user) {
            return ['status' => 'ignored'];
        }

        // Check if current license has expired (expires_at)
        $activeLicense = $user->userLicence;
        $metadata = is_array($scheduledDowngrade->metadata) ? $scheduledDowngrade->metadata : [];
        $scheduledDate = isset($metadata['scheduled_activation_date'])
            ? Carbon::parse($metadata['scheduled_activation_date'])
            : null;

        // Only apply if the license has expired
        $shouldApply = false;
        if ($activeLicense && $activeLicense->expires_at) {
            // License has expired - apply downgrade
            if ($activeLicense->expires_at->isPast() || $activeLicense->expires_at->isToday()) {
                $shouldApply = true;
            }
        } elseif ($scheduledDate) {
            // Fallback to scheduled date if no license expiry
            if ($scheduledDate->isPast() || $scheduledDate->isToday()) {
                $shouldApply = true;
            }
        }

        if (!$shouldApply) {
            Log::info('FastSpring downgrade not yet due - license still active', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'license_expires_at' => $activeLicense?->expires_at?->toDateTimeString(),
                'scheduled_date' => $scheduledDate?->toDateTimeString()
            ]);
            return ['status' => 'ignored', 'message' => 'Downgrade not yet due - current plan still active'];
        }

        return DB::transaction(function () use ($scheduledDowngrade, $subscriptionId, $metadata, $user) {
            $newPackage = $scheduledDowngrade->package;

            if (!$newPackage) {
                Log::error('FastSpring downgrade application failed - missing package', [
                    'order_id' => $scheduledDowngrade->id,
                    'user_id' => $scheduledDowngrade->user_id,
                    'package_id' => $scheduledDowngrade->package_id
                ]);
                return ['status' => 'failed', 'message' => 'Missing package'];
            }

            // NOW apply the downgrade to FastSpring API - current plan has expired
            $newProductPath = $metadata['fastspring_product_path'] ?? $this->getFastSpringProductPath($newPackage->name);

            $credentials = $this->getFastSpringCredentials();
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
                ->put("https://api.fastspring.com/subscriptions/{$subscriptionId}", [
                    'product' => $newProductPath,
                    'prorate' => false
                ]);

            if (!$response->successful()) {
                Log::error('Failed to apply FastSpring downgrade via API', [
                    'subscription_id' => $subscriptionId,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                // Continue with local update even if API call fails
            } else {
                Log::info('FastSpring subscription updated via API for downgrade', [
                    'subscription_id' => $subscriptionId,
                    'new_product' => $newProductPath
                ]);
            }

            // Update user's package
            $user->update([
                'package_id' => $newPackage->id
            ]);

            // Update license if exists
            $license = $user->userLicence;
            if ($license) {
                $license->update([
                    'package_id' => $newPackage->id,
                    'updated_at' => now()
                ]);
            }

            // Mark downgrade as processed
            $metadata['downgrade_processed'] = true;
            $metadata['processed_at'] = now()->toDateTimeString();
            $scheduledDowngrade->update([
                'status' => 'completed',
                'metadata' => $metadata
            ]);

            Log::info('FastSpring scheduled downgrade applied', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'new_package' => $newPackage->name,
                'order_id' => $scheduledDowngrade->id
            ]);

            return ['status' => 'processed', 'message' => 'Downgrade applied successfully'];
        });
    }

    protected function handleCancellation(array $payload): array
    {
        $subscriptionId = $payload['subscription'] ?? null;
        if (!$subscriptionId) {
            return ['status' => 'ignored'];
        }

        $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
        if ($userLicense) {
            $user = $userLicense->user;
            DB::transaction(function () use ($user, $userLicense, $subscriptionId) {
                $userLicense->delete();

                $user->update([
                    'is_subscribed' => false,
                    'subscription_id' => null,
                    'package_id' => null,
                    'payment_gateway_id' => null,
                    'user_license_id' => null
                ]);

                $order = Order::where('user_id', $user->id)
                    ->latest('created_at')
                    ->first();

                if ($order) {
                    $order->update(['status' => 'canceled']);
                }
            });
        }

        return ['status' => 'processed'];
    }

    public function processSuccessCallback(array $requestData): array
    {
        $orderId = $requestData['orderId'] ?? $requestData['order_id'] ?? null;
        $packageName = $requestData['package_name'] ?? null;

        if (!$orderId) {
            return ['success' => false, 'error' => 'Invalid order ID'];
        }

        $order = Order::where('transaction_id', $orderId)->first();
        if ($order && $order->status === 'completed') {
            return ['success' => true, 'already_completed' => true];
        }

        $response = $this->fastspringClient()
            ->get("https://api.fastspring.com/orders/{$orderId}");

        if (!$response->successful()) {
            return ['success' => false, 'error' => 'Order verification failed'];
        }

        $orderData = $response->json()['orders'][0] ?? $response->json();

        if (!isset($orderData['completed']) || !$orderData['completed']) {
            return ['success' => false, 'error' => 'Order not completed'];
        }

        $userId = auth()->id();
        $email = auth()->user()?->email;

        if (!$userId) {
            return ['success' => false, 'error' => 'Invalid payment data: user_id not found'];
        }

        if (!$packageName && isset($orderData['items'][0]['product'])) {
            $packageName = $orderData['items'][0]['product'];
        }

        if (!$packageName) {
            return ['success' => false, 'error' => 'Invalid payment data: package not found'];
        }

        $subscriptionData = $this->getSubscriptionId($orderId);

        if (isset($subscriptionData['error'])) {
            return ['success' => false, 'error' => $subscriptionData['error']];
        }

        $paymentData = array_merge($orderData, [
            'order_id' => $orderId,
            'package' => $packageName,
            'user_id' => $userId,
            'subscription_id' => $subscriptionData['id'] ?? null,
            'action' => 'new'
        ]);

        if (!$paymentData['user_id'] || !$paymentData['package']) {
            Log::error('FastSpring payment validation failed before processing', [
                'order_id' => $orderId,
                'user_id' => $paymentData['user_id'],
                'package' => $paymentData['package']
            ]);
            return ['success' => false, 'error' => 'Invalid payment data: missing required fields'];
        }

        try {
            $result = $this->processPayment($paymentData, false);
            if (!$result) {
                return ['success' => false, 'error' => 'Payment processing failed'];
            }
            return ['success' => $result, 'payment_data' => $paymentData];
        } catch (\Exception $e) {
            Log::error('FastSpring payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId,
                'user_id' => $userId,
                'package' => $packageName
            ]);
            return ['success' => false, 'error' => 'Payment processing failed: ' . $e->getMessage()];
        }
    }

    protected function getSubscriptionId(string $orderId)
    {
        $response = $this->fastspringClient()
            ->get("https://api.fastspring.com/orders/{$orderId}");

        if ($response->failed()) {
            return ['error' => 'Order verification failed.'];
        }

        $order = $response->json();
        $subscriptionId = $order['items'][0]['subscription'] ?? null;

        if (!$subscriptionId) {
            return ['error' => 'No subscription found for this order.'];
        }

        return $this->getSubscription($subscriptionId);
    }

    protected function getSubscription(string $subscriptionId): array
    {
        $response = $this->fastspringClient()
            ->get("https://api.fastspring.com/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            Log::error('Failed to retrieve FastSpring subscription', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return ['error' => 'Failed to retrieve subscription details'];
        }

        $data = $response->json();

        // FastSpring returns a "subscriptions" array – normalize to a single subscription record
        if (isset($data['subscriptions']) && is_array($data['subscriptions']) && count($data['subscriptions']) > 0) {
            return $data['subscriptions'][0];
        }

        return $data;
    }

    public function createUpgradeCheckout(User $user, Package $package, string $subscriptionId): string
    {
        $tempTransactionId = 'FS-UPGRADE-' . Str::random(10);
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

        $baseUrl = config('payment.gateways.FastSpring.base_url', 'https://sbl.onfastspring.com');
        $storefront = config('payment.gateways.FastSpring.storefront', 'livebuzzstudio.test.onfastspring.com/popup-test-87654-payment');
        return "{$baseUrl}/{$storefront}?product={$package->name}&subscription={$subscriptionId}&order_id={$order->id}";
    }

    public function createDowngradeCheckout(User $user, Package $package, string $subscriptionId, ?string $productId = null): string
    {
        Log::info('FastSpring createDowngradeCheckout called', [
            'user_id' => $user->id,
            'package_name' => $package->name,
            'subscription_id' => $subscriptionId
        ]);

        return DB::transaction(function () use ($user, $package, $subscriptionId) {
            // Cancel any existing scheduled downgrades
            Order::where('user_id', $user->id)
                ->where('order_type', 'downgrade')
                ->whereIn('status', ['pending', 'pending_downgrade', 'scheduled_downgrade'])
                ->update(['status' => 'cancelled']);

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId(),
                'order_type' => 'downgrade',
                'subscription_id' => $subscriptionId,
                'transaction_id' => 'FS-DOWNGRADE-' . Str::random(10),
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'downgrade_to' => $package->name,
                    'downgrade_type' => 'subscription_downgrade',
                ]
            ]);

            $baseUrl = config('payment.gateways.FastSpring.base_url', 'https://sbl.onfastspring.com');
            $storefront = config('payment.gateways.FastSpring.storefront', 'livebuzzstudio.test.onfastspring.com/popup-check-paymet');

            // Open FastSpring checkout popup
            return "{$baseUrl}/{$storefront}?product={$package->name}&subscription={$subscriptionId}&order_id={$order->id}&downgrade=true";
        });
    }


    protected function cancelFastSpringSubscription(string $subscriptionId, int $billingPeriod = 1)
    {
        if (!in_array($billingPeriod, [0, 1])) {
            throw new \InvalidArgumentException('Billing period must be 0 (immediate) or 1 (end of period)');
        }

        return $this->fastspringClient()
            ->delete("https://api.fastspring.com/subscriptions/{$subscriptionId}?billingPeriod={$billingPeriod}");
    }

    public function verifyTransaction(string $transactionId): ?array
    {
        $response = $this->fastspringClient()
            ->get("https://api.fastspring.com/orders/{$transactionId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function processAddonOrder(User $user, string $orderId, string $addon): array
    {
        $response = $this->fastspringClient()
            ->get("https://api.fastspring.com/orders/{$orderId}");

        if (!$response->successful()) {
            throw new \Exception('FastSpring order verification failed');
        }

        $orderJson = $response->json();
        $orderData = $orderJson['orders'][0] ?? $orderJson;

        if (!($orderData['completed'] ?? false)) {
            throw new \Exception('Add-on order not completed');
        }

        $amount = 0.0;
        if (isset($orderData['total']) && is_numeric($orderData['total'])) {
            $amount = (float)$orderData['total'];
        } elseif (isset($orderData['items'][0]['subtotal']) && is_numeric($orderData['items'][0]['subtotal'])) {
            $amount = (float)$orderData['items'][0]['subtotal'];
        } elseif (isset($orderData['totalDisplay'])) {
            $amount = (float)preg_replace('/[^\d.]/', '', (string)$orderData['totalDisplay']);
        }
        $currency = $orderData['currency'] ?? ($orderData['items'][0]['currency'] ?? 'USD');

        $addonKey = strtolower((string)$addon);
        $addonName = match ($addonKey) {
            'avatar_customization', 'avatar-customization' => 'Avatar Customization (Clone Yourself)',
            default => null,
        };
        $packageId = $addonName ? (\App\Models\Package::where('name', $addonName)->value('id')) : null;

        $paymentGatewayId = $this->getPaymentGatewayId();

        $existing = Order::where('transaction_id', $orderId)->first();
        if ($existing) {
            $existing->update([
                'status' => 'completed',
                'amount' => $amount > 0 ? $amount : $existing->amount,
                'currency' => $currency,
                'order_type' => 'addon',
                'payment_gateway_id' => $paymentGatewayId,
                'package_id' => $packageId ?? $existing->package_id,
                'metadata' => array_merge(($existing->metadata ?? []), [
                    'addon' => $addon,
                    'fastspring_order' => $orderData,
                ]),
            ]);
            $order = $existing;
        } else {
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageId,
                'payment_gateway_id' => $paymentGatewayId,
                'amount' => $amount,
                'transaction_id' => $orderId,
                'status' => 'completed',
                'currency' => $currency,
                'order_type' => 'addon',
                'metadata' => [
                    'addon' => $addon,
                    'fastspring_order' => $orderData,
                ],
            ]);
        }

        try {
            $resolved = $this->licenseApiService->resolvePlanLicense($user->tenant_id, $addonName ?? $addonKey, true);
            if ($resolved) {
                $licenseKey = $resolved['subscriptionCode'] ?? null;
                if ($licenseKey) {
                    $this->licenseApiService->addLicenseToTenant($user->tenant_id, $licenseKey);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Addon license fulfillment failed', [
                'user_id' => $user->id,
                'addon' => $addon,
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => true, 'order' => $order];
    }

    protected function getFastSpringProductPath(string $package): string
    {
        $mapping = [
            'Free' => 'free-plan',
            'Starter' => 'starter-plan',
            'Pro' => 'pro-plan',
            'Business' => 'business-plan',
            'Enterprise' => 'enterprise-plan',
        ];
        return $mapping[$package] ?? 'starter-plan';
    }

    /**
     * Determine when the current paid plan should end, for scheduling
     * downgrades or cancellations.
     */
    protected function resolvePlanEndDate(User $user, ?string $gatewayNextChargeDate = null): Carbon
    {
        $activeLicense = $user->userLicence;

        if ($activeLicense && $activeLicense->expires_at) {
            return $activeLicense->expires_at;
        }

        if ($gatewayNextChargeDate) {
            try {
                return Carbon::parse($gatewayNextChargeDate);
            } catch (\Throwable $e) {
            }
        }

        return now()->addDays(30);
    }

    /**
     * Build a FastSpring product identifier for the target package
     * based on the current subscription product string.
     *
     *
     */
    protected function buildFastSpringProductPathForPackage(string $currentProduct, string $targetPackageName): string
    {
        $targetSlug = strtolower($targetPackageName);
        $currentProduct = trim($currentProduct);

        if ($currentProduct === '') {
            return $this->getFastSpringProductPath($targetPackageName);
        }

        // Replace the leading segment (before first '-') with the target slug, preserving the suffix
        $newProduct = preg_replace('/^[^-\s]+/', $targetSlug, $currentProduct, 1);

        if (is_string($newProduct) && $newProduct !== '') {
            return $newProduct;
        }

        // Fallback to static mapping if regex replacement fails
        return $this->getFastSpringProductPath($targetPackageName);
    }

    public function createSession(Order $order): array
    {
        $packageName = $order->package->name ?? $order->metadata['package'] ?? 'Starter';
        return [
            'success' => true,
            'productPath' => $this->getFastSpringProductPath($packageName),
            'orderId' => $order->id
        ];
    }
}
