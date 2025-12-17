<?php

namespace App\Services\Payment;

use App\Factories\PaymentGatewayFactory;
use App\Models\{
    Order,
    User,
    Package,
    PaymentGateways,
};
use App\Models\UserLicence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\License\LicenseApiService;

class PaymentService
{

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private Order $order,
        private User $user,
        private LicenseApiService $licenseApiService,
    ) {}

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }
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

            // keep service-level order in sync when available
            $this->order = $order;

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
}

