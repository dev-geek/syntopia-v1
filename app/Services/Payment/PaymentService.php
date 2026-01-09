<?php

namespace App\Services\Payment;

use App\Factories\PaymentGatewayFactory;
use App\Models\{
    Order,
    User,
    Package,
    PaymentGateways,
    UserLicence,
};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;
use Illuminate\Support\Facades\Cache;

class PaymentService
{
    private ?Order $order = null;
    private ?User $user = null;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private LicenseApiService $licenseApiService,
        private PackageGatewayService $packageGatewayService,
        private FirstPromoterService $firstPromoterService,
    ) {}

    public function processPayment(array $paymentData, string $gatewayName, bool $returnRedirect = true): array
    {
        if (!isset($this->user) && isset($paymentData['user']) && $paymentData['user'] instanceof User) {
            $this->user = $paymentData['user'];
        }

        if (!isset($this->user)) {
            $this->user = Auth::user();
        }

        if (!$this->user) {
            throw new \RuntimeException('User not authenticated');
        }

        $isDowngrade = !empty($paymentData['is_downgrade']) || $this->isDowngrade($paymentData);

        if ($isDowngrade) {
            // Cancel any scheduled cancellation when user schedules a downgrade
            $this->cancelScheduledCancellation($this->user, 'downgrade');
            // Cancel any previous scheduled downgrades (new downgrade overrides previous)
            $this->cancelScheduledDowngrades($this->user, 'new_downgrade');

            $gateway = $this->gatewayFactory->create($gatewayName)->setUser($this->user);

            if (!method_exists($gateway, 'handleDowngrade')) {
                throw new \RuntimeException("Downgrade is not supported for gateway {$gatewayName}");
            }

            return $gateway->handleDowngrade($paymentData, $returnRedirect);
        }

        if (!isset($this->order) && !(isset($paymentData['order']) && $paymentData['order'] instanceof Order)) {
            $packageName = $paymentData['package'] ?? null;

            if (!$packageName) {
                throw new \InvalidArgumentException('Package is required for payment processing');
            }

            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
            if (!$package) {
                throw new \RuntimeException('Package not found');
            }

            $isAddonPackage = in_array($package->name, ['Avatar Customization (Clone Yourself)']);
            if ($isAddonPackage && strtolower($gatewayName) !== 'fastspring') {
                Log::warning('Addon purchase attempted with non-FastSpring gateway', [
                    'package' => $package->name,
                    'requested_gateway' => $gatewayName,
                    'user_id' => $this->user->id
                ]);
                $gatewayName = 'FastSpring';
            }

            $isUpgrade = $this->isUpgrade($paymentData);
            $paymentData['is_upgrade'] = $isUpgrade;

            $gatewayRecord = PaymentGateways::whereRaw('LOWER(name) = ?', [strtolower($gatewayName)])->first();

            $this->order = Order::create([
                'user_id'            => $this->user->id,
                'package_id'         => $package->id,
                'amount'             => $package->price,
                'currency'           => $package->currency ?? 'USD',
                'payment_gateway_id' => $gatewayRecord?->id,
                'status'             => 'pending',
                'order_type'         => $isUpgrade ? 'upgrade' : 'new',
                'transaction_id'     => null, // Will be set when transaction is created
            ]);

            $paymentData['order'] = $this->order;
        } elseif (!isset($this->order) && isset($paymentData['order']) && $paymentData['order'] instanceof Order) {
            $this->order = $paymentData['order'];
        }

        if (!isset($paymentData['is_upgrade'])) {
            $paymentData['is_upgrade'] = $this->isUpgrade($paymentData);
        }

        $gateway = $this->gatewayFactory->create($gatewayName)
                                        ->setUser($this->user)
                                        ->setOrder($this->order);

        return $gateway->processPayment($paymentData, $returnRedirect);
    }

    private function isDowngrade(array $paymentData): bool
    {
        if (!isset($paymentData['package']) || !$this->user || !$this->user->package) {
            return false;
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'];
        $targetPackage = Package::whereRaw('LOWER(name) = ?', [strtolower($targetPackageName)])->first();

        if (!$targetPackage) {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        return $targetPrice < $currentPrice;
    }

    private function isUpgrade(array $paymentData): bool
    {
        if (!isset($paymentData['package']) || !$this->user || !$this->user->package) {
            return false;
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'];
        $targetPackage = Package::whereRaw('LOWER(name) = ?', [strtolower($targetPackageName)])->first();

        if (!$targetPackage) {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        return $targetPrice > $currentPrice;
    }

    public function detectGatewayFromUser(User $user, ?string $subscriptionId = null): ?PaymentGateways
    {
        if ($user->paymentGateway) {
            return $user->paymentGateway;
        }

        if ($subscriptionId) {
            $license = UserLicence::where('subscription_id', $subscriptionId)->first();

            if ($license && $license->paymentGateway) {
                return $license->paymentGateway;
            }
        }

        $activeGateway = PaymentGateways::where('is_active', true)->first();

        if ($activeGateway) {
            return $activeGateway;
        }

        return PaymentGateways::first();
    }

    public function processSuccessCallback(string $gateway, array $payload): array
    {
        $user = Auth::user();

        if (!$user) {
            throw new \RuntimeException('User not authenticated for success callback');
        }

        $packageName = $payload['package_name'] ?? $payload['package'] ?? null;

        // If package name is missing, try to retrieve it from the order using transaction_id
        if (!$packageName) {
            $transactionId = $payload['transaction_id']
                ?? $payload['orderId']
                ?? $payload['ORDER_ID']
                ?? null;

            if ($transactionId) {
                $order = Order::where('transaction_id', $transactionId)
                    ->where('user_id', $user->id)
                    ->first();

                if ($order && $order->package) {
                    $packageName = $order->package->name;
                }
            }
        }

        if (!$packageName) {
            throw new \InvalidArgumentException('Package name missing from success callback');
        }

        $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
        if (!$package) {
            throw new \RuntimeException('Package not found for success callback');
        }

        $transactionId = $payload['transaction_id']
            ?? $payload['orderId']
            ?? $payload['ORDER_ID']
            ?? null;
        if (!$transactionId) {
            throw new \InvalidArgumentException('Transaction ID missing from success callback');
        }

        $gatewayRecord = PaymentGateways::whereRaw('LOWER(name) = ?', [strtolower($gateway)])->first();

        return DB::transaction(function () use ($user, $package, $transactionId, $payload, $gatewayRecord) {
            $currentPackage = $user->package;
            $currentPrice = $currentPackage?->price ?? 0;
            $newPrice = $package->price ?? 0;
            $isUpgrade = $newPrice > $currentPrice;
            $isDowngrade = $newPrice < $currentPrice && $currentPrice > 0;

            if ($isUpgrade || $isDowngrade) {
                // Cancel scheduled cancellations (upgrade/downgrade overrides cancellation)
                $this->cancelScheduledCancellation($user, $isUpgrade ? 'upgrade' : 'downgrade');

                if ($isUpgrade) {
                    // Cancel scheduled downgrades (upgrade overrides downgrade)
                    $this->cancelScheduledDowngrades($user, 'upgrade');
                } elseif ($isDowngrade) {
                    // Cancel previous scheduled downgrades (new downgrade overrides previous downgrade)
                    $this->cancelScheduledDowngrades($user, 'new_downgrade');
                }
            }

            // Check if there's a scheduled cancellation that should take precedence
            $scheduledCancellationOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('status', 'cancelled')
                ->whereJsonContains('metadata->cancellation_scheduled', true)
                ->latest('created_at')
                ->first();

            $scheduledDowngradeOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->where('status', 'scheduled_downgrade')
                ->when($gatewayRecord, fn ($q) => $q->where('payment_gateway_id', $gatewayRecord->id))
                ->latest('created_at')
                ->first();

            // If there's a scheduled cancellation, cancel the scheduled downgrade and don't process it
            if ($scheduledDowngradeOrder && $scheduledCancellationOrder) {

                $scheduledDowngradeOrder->update([
                    'status' => 'cancelled',
                    'metadata' => array_merge($scheduledDowngradeOrder->metadata ?? [], [
                        'cancelled_reason' => 'Scheduled cancellation takes precedence over downgrade',
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => 'scheduled_cancellation_precedence',
                        'original_status' => $scheduledDowngradeOrder->status,
                    ]),
                ]);

                $scheduledDowngradeOrder = null;
            }

            if ($scheduledDowngradeOrder && $isDowngrade) {
                $targetPackagePrice = $scheduledDowngradeOrder->metadata['target_package_price'] ?? $package->price;

                $scheduledDowngradeOrder->update([
                    'status'         => 'completed',
                    'amount'          => $targetPackagePrice, // Update from 0 to actual charged amount
                    'transaction_id'  => $transactionId,
                    'completed_at'    => now(),
                    'metadata'        => array_merge($scheduledDowngradeOrder->metadata ?? [], [
                        'activated_at' => now()->toISOString(),
                        'payment_processed_at' => now()->toISOString(),
                        'gateway' => $gatewayRecord?->name,
                        'raw_payload' => $payload,
                    ]),
                ]);

                $order = $scheduledDowngradeOrder;
            } else {
                // Regular order processing (new subscription or upgrade)
                $order = Order::query()
                    ->where('user_id', $user->id)
                    ->where('package_id', $package->id)
                    ->when($gatewayRecord, fn ($q) => $q->where('payment_gateway_id', $gatewayRecord->id))
                    ->where('status', 'pending')
                    ->latest('created_at')
                    ->first();

                if ($order) {
                    $order->update([
                        'status'         => 'completed',
                        'transaction_id' => $transactionId,
                        'metadata'       => [
                            'source'      => 'gateway_success_callback',
                            'gateway'     => $gatewayRecord?->name,
                            'raw_payload' => $payload,
                        ],
                    ]);
                } else {
                    $order = Order::create([
                        'user_id'            => $user->id,
                        'package_id'         => $package->id,
                        'amount'             => $package->price,
                        'currency'           => $package->currency ?? 'USD',
                        'payment_gateway_id' => $gatewayRecord?->id,
                        'status'             => 'completed',
                        'order_type'         => $isUpgrade ? 'upgrade' : 'new',
                        'transaction_id'     => $transactionId,
                        'metadata'           => [
                            'source'      => 'gateway_success_callback',
                            'gateway'     => $gatewayRecord?->name,
                            'raw_payload' => $payload,
                        ],
                    ]);
                }
            }

            $paymentGatewayId = $gatewayRecord?->id ?? $user->payment_gateway_id;

            if (!$user->payment_gateway_id && $gatewayRecord) {
                $paymentGatewayId = $gatewayRecord->id;
            }

            $user->update([
                'package_id'         => $package->id,
                'payment_gateway_id' => $paymentGatewayId,
                'is_subscribed'      => true,
            ]);

            try {
                $license = $this->licenseApiService->createAndActivateLicense(
                    $user->fresh(),
                    $package,
                    $transactionId,
                    $gatewayRecord?->id,
                    $isUpgrade
                );

                if (!$license) {
                    Log::error('Failed to create license after payment success', [
                        'user_id'       => $user->id,
                        'package_id'    => $package->id,
                        'gateway_id'    => $gatewayRecord?->id,
                        'transaction_id'=> $transactionId,
                        'tenant_id'     => $user->tenant_id
                    ]);

                    throw new \RuntimeException('THIRD_PARTY_API_ERROR');
                }
            } catch (\Throwable $e) {
                Log::error('Exception while creating license after payment success', [
                    'user_id'       => $user->id,
                    'package_id'    => $package->id,
                    'gateway_id'    => $gatewayRecord?->id,
                    'transaction_id'=> $transactionId,
                    'error'         => $e->getMessage(),
                    'trace'         => $e->getTraceAsString()
                ]);

                if (str_contains($e->getMessage(), 'THIRD_PARTY_API_ERROR')) {
                    throw new \RuntimeException('THIRD_PARTY_API_ERROR');
                }

                throw new \RuntimeException('license_api_failed');
            }

            // Call public route after successful PayProGlobal payment
            if (strtolower($gatewayRecord?->name ?? '') === 'pay pro global') {
                try {
                    Http::post(route('public.submit'), [
                        'gateway' => 'payproglobal',
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                        'package' => $package->name,
                        'amount' => $order->amount,
                        'status' => 'completed',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[PaymentService] Failed to call public route after PayProGlobal payment', [
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Track FirstPromoter sale for Paddle and PayProGlobal
            $this->trackFirstPromoterSale($order->fresh(), $user, $package, $gatewayRecord);

            return [
                'success'      => true,
                'user'         => $user->fresh(),
                'package'      => $package,
                'order'        => $order->fresh(),
                'is_upgrade'   => $isUpgrade,
                'package_name' => $package->name,
            ];
        });
    }

    public function createFreePlanOrder(User $user, Package $package): Order
    {
        $paymentGatewayId = $user->payment_gateway_id;
        $gateway = $paymentGatewayId ? PaymentGateways::find($paymentGatewayId) : null;

        $paddleTransactionId = null;
        $paddleStatus = null;
        $paddleSubscriptionId = null;
        $paddleInvoiceId = null;
        $paddleInvoiceNumber = null;

        if ($gateway && strtolower($gateway->name) === 'paddle' && $user->paddle_customer_id) {
            try {
                $apiKey = config('payment.gateways.Paddle.api_key');
                $apiBaseUrl = rtrim(config('payment.gateways.Paddle.api_url', 'https://sandbox-api.paddle.com/'), '/');

                $paddleGateway = app(\App\Services\Payment\Gateways\PaddlePaymentGateway::class);

                $priceId = $this->packageGatewayService->getPaddlePriceId($package, $paddleGateway);
                $transactionData = [
                    'items' => [
                        [
                            'price_id' => $priceId,
                            'quantity' => 1
                        ]
                    ],
                    'customer_id' => $user->paddle_customer_id,
                    'currency_code' => 'USD',
                    'collection_mode' => 'automatic',
                    'address_id' => null,
                    'business_id' => null,
                    'discount_id' => null,
                    'custom_data' => [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'source' => 'free_plan_assignment'
                    ],
                ];

                $url = $apiBaseUrl . '/transactions';
                $response = Http::withToken($apiKey)
                    ->withHeaders([
                        'accept' => 'application/json',
                        'content-type' => 'application/json'
                    ])
                    ->post($url, $transactionData);

                if ($response && $response->successful()) {
                    $responseData = $response->json();
                    $transactionData = $responseData['data'] ?? [];
                    $paddleTransactionId = $transactionData['id'] ?? null;
                    $paddleStatus = $transactionData['status'] ?? null;
                    $paddleSubscriptionId = $transactionData['subscription_id'] ?? null;
                    $paddleInvoiceId = $transactionData['invoice_id'] ?? null;
                    $paddleInvoiceNumber = $transactionData['invoice_number'] ?? null;
                } else {
                    Log::warning('[PaymentService::createFreePlanOrder] Failed to create Paddle transaction for free plan', [
                        'user_id' => $user->id,
                        'status' => $response?->status(),
                        'response' => $response?->body()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('[PaymentService::createFreePlanOrder] Exception creating Paddle transaction', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (
            $gateway
            && strtolower($gateway->name) === 'paddle'
            && app()->environment('local')
            && !$paddleTransactionId
        ) {
            throw new \RuntimeException('[PaymentService::createFreePlanOrder] Paddle transaction_id is missing for free plan in local environment');
        }

        $metadata = [
            'source' => 'free_plan_immediate_assignment',
            'assigned_at' => now()->toISOString()
        ];

        if ($paddleTransactionId) {
            $metadata['paddle_transaction_id'] = $paddleTransactionId;
            if ($paddleStatus) {
                $metadata['paddle_status'] = $paddleStatus;
            }
            if ($paddleSubscriptionId) {
                $metadata['paddle_subscription_id'] = $paddleSubscriptionId;
            }
            if ($paddleInvoiceId) {
                $metadata['paddle_invoice_id'] = $paddleInvoiceId;
            }
            if ($paddleInvoiceNumber) {
                $metadata['paddle_invoice_number'] = $paddleInvoiceNumber;
            }
        }

        return Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_gateway_id' => $paymentGatewayId,
            'transaction_id' => $paddleTransactionId ? $paddleTransactionId : 'FREE-' . Str::random(10),
            'metadata' => $metadata
        ]);
    }

    public function processAddonSuccess(User $user, string $orderId, string $addon): array
    {

        $addonPackage = Package::whereIn('name', ['Avatar Customization (Clone Yourself)'])->first();

        if (!$addonPackage) {
            Log::error('[PaymentService::processAddonSuccess] Addon package not found', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'addon' => $addon,
            ]);
            throw new \RuntimeException('Add-on package not found');
        }

        $fastSpringGateway = PaymentGateways::where('name', 'FastSpring')->first();

        if (!$fastSpringGateway) {
            Log::error('[PaymentService::processAddonSuccess] FastSpring gateway not found', [
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);
            throw new \RuntimeException('FastSpring payment gateway not found');
        }

        $order = Order::where('transaction_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $addonPackage->id,
                'amount' => $addonPackage->price,
                'currency' => $addonPackage->currency ?? 'USD',
                'payment_gateway_id' => $fastSpringGateway->id,
                'status' => 'completed',
                'order_type' => 'addon',
                'transaction_id' => $orderId,
                'metadata' => [
                    'addon' => $addon,
                    'source' => 'fastspring_addon_success',
                    'processed_at' => now()->toISOString(),
                ],
            ]);
        } else {
            if ($order->status === 'completed') {
                return [
                    'success' => true,
                    'message' => 'Add-on purchase was already processed successfully',
                ];
            }

            $metadata = $order->metadata ?? [];
            $metadata['addon'] = $addon;
            $metadata['processed_at'] = now()->toISOString();

            $order->update([
                'status' => 'completed',
                'order_type' => 'addon',
                'metadata' => $metadata,
            ]);
        }

        $packageName = $order->package->name ?? $addon;

        return [
            'success' => true,
            'message' => "Successfully purchased {$packageName} add-on!",
        ];
    }

    // handle package cancellation
    /**
     * Cancel any scheduled cancellation orders for a user
     * Used when user upgrades/downgrades to override scheduled cancellation
     *
     * @param User $user
     * @param string $reason Reason for cancelling the scheduled cancellation (e.g., 'upgrade', 'downgrade')
     * @return void
     */
    public function cancelScheduledCancellation(User $user, string $reason = 'subscription_change'): void
    {
        // Get ALL scheduled cancellation orders, not just the latest
        // This handles cases where multiple cancellation orders exist after subscription changes
        $scheduledCancellationOrders = Order::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->whereJsonContains('metadata->cancellation_scheduled', true)
            ->get();

        if ($scheduledCancellationOrders->isNotEmpty()) {
            foreach ($scheduledCancellationOrders as $scheduledCancellationOrder) {
                $scheduledCancellationOrder->update([
                    'metadata' => array_merge($scheduledCancellationOrder->metadata ?? [], [
                        'cancelled_reason' => "User {$reason} subscription",
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => $reason,
                        'overwritten_at' => now()->toISOString(),
                        'cancellation_scheduled' => false, // Mark as no longer scheduled
                    ]),
                ]);
            }

            // Update license status if it was set to cancelled_at_period_end
            $activeLicense = $user->userLicence;
            if ($activeLicense && $activeLicense->status === 'cancelled_at_period_end') {
                $activeLicense->update([
                    'status' => 'active',
                ]);
            }

            // Cancel scheduled cancellation in the payment gateway
            $gatewayRecord = $user->paymentGateway;
            if ($gatewayRecord && $activeLicense && $activeLicense->subscription_id) {
                try {
                    $gatewayName = strtolower($gatewayRecord->name);
                    $gateway = $this->gatewayFactory->create($gatewayRecord->name)->setUser($user);

                    // Call the appropriate method based on gateway
                    if ($gatewayName === 'paddle') {
                        /** @var \App\Services\Payment\Gateways\PaddlePaymentGateway $gateway */
                        if (method_exists($gateway, 'cancelScheduledCancellationInPaddle')) {
                            $gateway->cancelScheduledCancellationInPaddle($activeLicense->subscription_id);
                        }
                    } elseif ($gatewayName === 'pay pro global') {
                        /** @var \App\Services\Payment\Gateways\PayProGlobalPaymentGateway $gateway */
                        if (method_exists($gateway, 'cancelScheduledCancellationInPayProGlobal')) {
                            $gateway->cancelScheduledCancellationInPayProGlobal($activeLicense->subscription_id);
                        }
                    } elseif ($gatewayName === 'fastspring') {
                        /** @var \App\Services\Payment\Gateways\FastSpringPaymentGateway $gateway */
                        if (method_exists($gateway, 'cancelScheduledCancellationInFastSpring')) {
                            $gateway->cancelScheduledCancellationInFastSpring($activeLicense->subscription_id);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('[PaymentService::cancelScheduledCancellation] Failed to cancel scheduled cancellation in gateway', [
                        'user_id' => $user->id,
                        'gateway' => $gatewayRecord->name,
                        'subscription_id' => $activeLicense->subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Cancel any pending or scheduled downgrade orders for a user.
     * Used when an upgrade happens and should override previous downgrade scheduling.
     *
     * @param User $user
     * @param string $reason
     * @return void
     */
    public function cancelScheduledDowngrades(User $user, string $reason = 'subscription_change'): void
    {
        $scheduledDowngrades = Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'scheduled_downgrade'])
            ->get();

        foreach ($scheduledDowngrades as $downgradeOrder) {
            $originalStatus = $downgradeOrder->status;

            $downgradeOrder->update([
                'status' => 'cancelled',
                'metadata' => array_merge($downgradeOrder->metadata ?? [], [
                    'cancelled_reason' => "User {$reason} subscription (upgrade overrides downgrade)",
                    'cancelled_at' => now()->toISOString(),
                    'cancelled_by' => $reason,
                    'original_status' => $originalStatus,
                ]),
            ]);
        }
    }

    public function handleSubscriptionCancellation(User $user): array
    {
        $activeLicense = $user->userLicence;

        if (!$activeLicense) {
            Log::warning('[PaymentService::handleSubscriptionCancellation] No active license found', [
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'message' => 'No active subscription found to cancel'
            ];
        }

        $subscriptionId = $activeLicense->subscription_id;

        $gatewayRecord = $this->detectGatewayFromUser($user, $subscriptionId);

        if (!$gatewayRecord) {
            Log::error('[PaymentService::handleSubscriptionCancellation] Gateway not found', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
            ]);
            return [
                'success' => false,
                'message' => 'Payment gateway not found for this subscription'
            ];
        }

        $gateway = $this->gatewayFactory->create($gatewayRecord->name)
            ->setUser($user);

        if (!method_exists($gateway, 'handleCancellation')) {
            Log::error('[PaymentService::handleSubscriptionCancellation] Cancellation not supported', [
                'user_id' => $user->id,
                'gateway_name' => $gatewayRecord->name,
            ]);
            return [
                'success' => false,
                'message' => "Cancellation is not supported for gateway {$gatewayRecord->name}"
            ];
        }

        // Cancel any previous scheduled cancellations (new cancellation overrides previous cancellation)
        $this->cancelScheduledCancellation($user, 'new_cancellation');
        // Cancel any scheduled downgrades (cancellation overrides downgrade)
        $this->cancelScheduledDowngrades($user, 'cancellation');

        $result = $gateway->handleCancellation($user, $subscriptionId);

        return $result;
    }

    public function handlePayProGlobalAuthToken(string $authToken, $request = null): ?\App\Models\User
    {
        $userId = \Illuminate\Support\Facades\Cache::get("paypro_auth_token_{$authToken}");
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('User')) {
            Auth::guard('web')->login($user, true);
            if ($request && method_exists($request, 'session')) {
                $request->session()->save();
            }
            Cache::forget("paypro_auth_token_{$authToken}");
            return $user;
        }

        return null;
    }

    public function extractGatewayFromRequest(array $requestData, ?string $successUrl = null): ?string
    {
        $gateway = $requestData['gateway'] ?? $requestData['gateway'] ?? null;

        if (empty($gateway) && $successUrl) {
            $queryString = parse_url($successUrl, PHP_URL_QUERY);
            if ($queryString) {
                parse_str($queryString, $queryParams);
                $gateway = $queryParams['gateway'] ?? null;
            }
        }

        return $gateway;
    }

    public function prepareSuccessCallbackPayload(array $requestData, array $queryData): array
    {
        $payload = array_merge(
            $requestData,
            $queryData
        );

        // Extract transaction_id
        $transactionId = $queryData['transaction_id']
            ?? $requestData['transaction_id']
            ?? $requestData['ORDER_ID']
            ?? $queryData['ORDER_ID']
            ?? $requestData['orderId']
            ?? $queryData['orderId']
            ?? null;

        if ($transactionId) {
            $payload['transaction_id'] = $transactionId;
            $payload['orderId'] = $transactionId;
        }

        // Extract package name
        $packageName = $payload['package'] ?? $payload['package_name'] ?? null;
        if (!$packageName || $packageName === '{package}') {
            $customData = $requestData['custom'] ?? $queryData['custom'] ?? null;
            if ($customData) {
                try {
                    $custom = is_string($customData) ? json_decode($customData, true) : $customData;
                    if (is_array($custom) && isset($custom['package'])) {
                        $payload['package'] = $custom['package'];
                        $payload['package_name'] = $custom['package'];
                    }
                } catch (\Throwable $e) {
                    Log::warning('[PaymentService] Failed to parse custom field for package name', [
                        'custom' => $customData,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $payload;
    }

    public function handleSuccessResponse(array $result, string $gateway): \Illuminate\Http\RedirectResponse
    {
        if (!$result['success']) {
            return redirect()->route('subscription')->with('error', $result['error'] ?? 'Payment processing failed');
        }

        if (isset($result['already_completed'])) {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->route('login')->with('info', 'Payment successful! Please log in to access your subscription.');
            }
            return redirect()->route('user.subscription.details')->with('success', 'Subscription active');
        }

        if (!\Illuminate\Support\Facades\Auth::check()) {
            return redirect()->route('login')->with('info', 'Payment successful! Please log in to access your subscription.');
        }

        $message = $result['is_upgrade']
            ? 'Subscription upgraded successfully!'
            : 'Payment successful! Your subscription is now active.';

        return redirect()->route('user.subscription.details')->with('success', $message);
    }

    public function getHttpStatusCode(string $errorMessage): int
    {
        return match(true) {
            str_contains($errorMessage, 'not authenticated') => 401,
            str_contains($errorMessage, 'not found') => 400,
            str_contains($errorMessage, 'restricted') => 403,
            str_contains($errorMessage, 'unavailable') => 409,
            default => 500
        };
    }

    public function getUserFriendlyErrorMessage(string $message): string
    {
        if (str_contains($message, 'THIRD_PARTY_API_ERROR')) {
            return 'We are experiencing some issues, please try later';
        }

        return match(true) {
            str_contains($message, 'not authenticated') => 'User not authenticated',
            str_contains($message, 'not found') => 'Resource not found',
            str_contains($message, 'restricted') => 'Plan Change Restricted',
            str_contains($message, 'configuration') => 'License Configuration Issue',
            str_contains($message, 'unavailable') => 'Licenses temporarily unavailable',
            str_contains($message, 'required') => 'Subscription Required',
            default => 'We are experiencing some issues, please try later'
        };
    }

    public function processSuccessCallbackWithAuth(array $requestData, array $queryData, ?string $successUrl = null, $request = null): array
    {
        $gateway = $this->extractGatewayFromRequest($requestData, $successUrl);
        if (empty($gateway)) {
            \Illuminate\Support\Facades\Log::error('No gateway specified in success callback', [
                'request_data' => $requestData,
                'query_data' => $queryData,
                'success_url' => $successUrl
            ]);
            throw new \InvalidArgumentException('Invalid payment gateway');
        }

        // Try to authenticate user if not already authenticated
        if (!\Illuminate\Support\Facades\Auth::check()) {
            $authToken = $requestData['auth_token'] ?? $queryData['auth_token'] ?? null;

            // Try auth token first (for Pay Pro Global)
            if ($authToken) {
                $this->handlePayProGlobalAuthToken($authToken, $request);
            }

            if (!\Illuminate\Support\Facades\Auth::check() && strtolower($gateway) === 'payproglobal') {
                $userId = $requestData['user_id'] ?? $queryData['user_id'] ?? null;

                // If user_id is a placeholder or not found, try to extract from custom field
                if (!$userId || $userId === '{user_id}' || !is_numeric($userId)) {
                    $customData = $requestData['custom'] ?? $queryData['custom'] ?? null;
                    if ($customData) {
                        try {
                            $custom = is_string($customData) ? json_decode($customData, true) : $customData;
                            if (is_array($custom) && isset($custom['user_id'])) {
                                $userId = $custom['user_id'];
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('[PaymentService] Failed to parse custom field for user_id', [
                                'custom' => $customData,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                if ($userId && is_numeric($userId) && $userId !== '{user_id}') {
                    $user = \App\Models\User::find((int) $userId);
                    if ($user && method_exists($user, 'hasRole') && $user->hasRole('User')) {
                        \Illuminate\Support\Facades\Auth::guard('web')->login($user, true);
                        if ($request && method_exists($request, 'session')) {
                            $request->session()->save();
                        }
                    }
                }
            }
        }

        $payload = $this->prepareSuccessCallbackPayload($requestData, $queryData);
        $result = $this->processSuccessCallback($gateway, $payload);

        if (!isset($result['success'])) {
            \Illuminate\Support\Facades\Log::error('Invalid response from processSuccessCallback', [
                'result' => $result,
                'gateway' => $gateway
            ]);
            throw new \RuntimeException('Payment processing failed: Invalid response from payment service');
        }

        return array_merge($result, ['gateway' => $gateway]);
    }

    public function processAddonSuccessWithValidation($user, ?string $orderId, ?string $addon): array
    {
        if (!$orderId || !$addon) {
            throw new \InvalidArgumentException('Invalid add-on payment parameters');
        }

        return $this->processAddonSuccess($user, $orderId, $addon);
    }

    private function trackFirstPromoterSale(Order $order, User $user, Package $package, ?PaymentGateways $gatewayRecord): void
    {
        if (!$order->transaction_id || $order->amount <= 0) {
            Log::debug('[PaymentService] Skipping FirstPromoter tracking: missing transaction_id or invalid amount', [
                'order_id' => $order->id,
                'transaction_id' => $order->transaction_id,
                'amount' => $order->amount,
            ]);
            return;
        }

        $gatewayName = strtolower($gatewayRecord?->name ?? '');

        // Only track for Paddle and PayProGlobal
        if (!in_array($gatewayName, ['paddle', 'pay pro global'])) {
            Log::debug('[PaymentService] Skipping FirstPromoter tracking: unsupported gateway', [
                'gateway' => $gatewayName,
            ]);
            return;
        }

        $metadata = $order->metadata ?? [];
        $rawPayload = $metadata['raw_payload'] ?? [];

        // Extract custom data from different sources based on gateway
        $customData = null;

        if ($gatewayName === 'paddle') {
            // For Paddle: check raw_payload first, then metadata
            $customData = $rawPayload['custom_data']
                ?? $rawPayload['custom']
                ?? $metadata['custom_data']
                ?? $metadata['custom']
                ?? null;
        } elseif ($gatewayName === 'pay pro global') {
            // For PayProGlobal: check raw_payload custom field (JSON string)
            $customDataString = $rawPayload['custom'] ?? $metadata['custom'] ?? null;
            if ($customDataString) {
                if (is_string($customDataString)) {
                    try {
                        $customData = json_decode($customDataString, true);
                    } catch (\Throwable $e) {
                        Log::warning('[PaymentService] Failed to parse PayProGlobal custom data', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } elseif (is_array($customDataString)) {
                    $customData = $customDataString;
                }
            }

            // Also check direct custom_data fields
            if (!$customData) {
                $customData = $rawPayload['custom_data'] ?? $metadata['custom_data'] ?? null;
            }
        }

        if (is_string($customData)) {
            try {
                $customData = json_decode($customData, true);
            } catch (\Throwable $e) {
                Log::warning('[PaymentService] Failed to parse custom data string', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $customData = null;
            }
        }

        if (!is_array($customData)) {
            $customData = [];
        }

        // Extract tracking IDs from custom data or metadata
        $tid = $customData['fp_tid']
            ?? $customData['tid']
            ?? $metadata['fp_tid']
            ?? $metadata['tid']
            ?? null;

        $refId = $customData['ref_id']
            ?? $metadata['ref_id']
            ?? null;

        // Prepare tracking data
        $trackingData = [
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'email' => $user->email,
            'uid' => (string) $user->id,
        ];

        // Add plan if available
        if ($package && $package->name) {
            $trackingData['plan'] = $package->name;
        }

        // Add tracking IDs if available
        if ($tid) {
            $trackingData['tid'] = $tid;
        }

        if ($refId) {
            $trackingData['ref_id'] = $refId;
        }

        try {
            $result = $this->firstPromoterService->trackSale($trackingData);

            if ($result === null) {
                Log::warning('[PaymentService] FirstPromoter tracking returned null', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'transaction_id' => $order->transaction_id,
                    'gateway' => $gatewayName,
                ]);
            } elseif (isset($result['duplicate']) && $result['duplicate']) {
                Log::info('[PaymentService] FirstPromoter tracking: duplicate sale detected', [
                    'order_id' => $order->id,
                    'transaction_id' => $order->transaction_id,
                    'gateway' => $gatewayName,
                ]);
            } else {
                Log::info('[PaymentService] FirstPromoter sale tracked successfully', [
                    'order_id' => $order->id,
                    'transaction_id' => $order->transaction_id,
                    'gateway' => $gatewayName,
                    'sale_id' => $result['id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[PaymentService] Failed to track FirstPromoter sale', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'transaction_id' => $order->transaction_id,
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

