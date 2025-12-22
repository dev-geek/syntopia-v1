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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\License\LicenseApiService;

class PaymentService
{
    private ?Order $order = null;
    private ?User $user = null;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private LicenseApiService $licenseApiService,
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

            $gatewayRecord = PaymentGateways::whereRaw('LOWER(name) = ?', [strtolower($gatewayName)])->first();

            $this->order = Order::create([
                'user_id'            => $this->user->id,
                'package_id'         => $package->id,
                'amount'             => $package->price,
                'currency'           => $package->currency ?? 'USD',
                'payment_gateway_id' => $gatewayRecord?->id,
                'status'             => 'pending',
                'order_type'         => !empty($paymentData['is_upgrade']) ? 'upgrade' : 'new',
                'transaction_id'     => strtoupper($gatewayName) . '-PENDING-' . Str::uuid(),
            ]);

            $paymentData['order'] = $this->order;
        } elseif (!isset($this->order) && isset($paymentData['order']) && $paymentData['order'] instanceof Order) {
            $this->order = $paymentData['order'];
        }

        $gateway = $this->gatewayFactory->create($gatewayName)
                                        ->setUser($this->user)
                                        ->setOrder($this->order);

        return $gateway->processPayment($paymentData, $returnRedirect);
    }

    public function detectGatewayFromUser(User $user, ?string $subscriptionId = null): ?PaymentGateways
    {
        if ($user->paymentGateway && $user->paymentGateway->is_active) {
            return $user->paymentGateway;
        }

        if ($subscriptionId) {
            $license = UserLicence::where('subscription_id', $subscriptionId)->first();

            if ($license && $license->paymentGateway && $license->paymentGateway->is_active) {
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
        if (!$packageName) {
            throw new \InvalidArgumentException('Package name missing from success callback');
        }

        $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
        if (!$package) {
            throw new \RuntimeException('Package not found for success callback');
        }

        $transactionId = $payload['transaction_id'] ?? $payload['orderId'] ?? null;
        if (!$transactionId) {
            throw new \InvalidArgumentException('Transaction ID missing from success callback');
        }

        $gatewayRecord = PaymentGateways::whereRaw('LOWER(name) = ?', [strtolower($gateway)])->first();

        return DB::transaction(function () use ($user, $package, $transactionId, $payload, $gatewayRecord) {
            $currentPackage = $user->package;
            $currentPrice = $currentPackage?->price ?? 0;
            $newPrice = $package->price ?? 0;
            $isUpgrade = $newPrice > $currentPrice;

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

            $user->update([
                'package_id'         => $package->id,
                'payment_gateway_id' => $gatewayRecord?->id ?? $user->payment_gateway_id,
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
                    ]);

                    throw new \RuntimeException('license_api_failed');
                }
            } catch (\Throwable $e) {
                Log::error('Exception while creating license after payment success', [
                    'user_id'       => $user->id,
                    'package_id'    => $package->id,
                    'gateway_id'    => $gatewayRecord?->id,
                    'transaction_id'=> $transactionId,
                    'error'         => $e->getMessage(),
                ]);

                throw new \RuntimeException('license_api_failed');
            }

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
        return Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'completed',
            'transaction_id' => 'FREE-' . Str::random(10),
            'metadata' => [
                'source' => 'free_plan_immediate_assignment',
                'assigned_at' => now()->toISOString()
            ]
        ]);
    }

    public function processAddonSuccess(User $user, string $orderId, string $addon): array
    {
        Log::info('[PaymentService::processAddonSuccess] Processing addon success', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'addon' => $addon,
        ]);

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
            Log::info('[PaymentService::processAddonSuccess] Order not found, creating new order', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'addon' => $addon,
            ]);

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

            Log::info('[PaymentService::processAddonSuccess] Addon order created', [
                'order_id' => $order->id,
                'transaction_id' => $orderId,
                'addon' => $addon,
            ]);
        } else {
            if ($order->status === 'completed') {
                Log::info('[PaymentService::processAddonSuccess] Order already completed', [
                    'order_id' => $order->id,
                    'transaction_id' => $orderId,
                ]);
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

            Log::info('[PaymentService::processAddonSuccess] Addon order completed', [
                'order_id' => $order->id,
                'transaction_id' => $orderId,
                'addon' => $addon,
            ]);
        }

        $packageName = $order->package->name ?? $addon;

        return [
            'success' => true,
            'message' => "Successfully purchased {$packageName} add-on!",
        ];
    }

    // handle package cancellation
    public function handleSubscriptionCancellation(User $user): array
    {
        Log::info('[PaymentService::handleSubscriptionCancellation] Starting cancellation', [
            'user_id' => $user->id,
        ]);

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
        Log::info('[PaymentService::handleSubscriptionCancellation] Found subscription', [
            'user_id' => $user->id,
            'subscription_id' => $subscriptionId,
        ]);

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

        Log::info('[PaymentService::handleSubscriptionCancellation] Gateway detected', [
            'user_id' => $user->id,
            'gateway_name' => $gatewayRecord->name,
        ]);

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

        Log::info('[PaymentService::handleSubscriptionCancellation] Calling gateway cancellation', [
            'user_id' => $user->id,
            'gateway_name' => $gatewayRecord->name,
            'subscription_id' => $subscriptionId,
        ]);

        return $gateway->handleCancellation($user, $subscriptionId);
    }
}

