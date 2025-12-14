<?php

namespace App\Factories;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Services\Payment\Gateways\FastSpringPaymentGateway;
use App\Services\Payment\Gateways\PaddlePaymentGateway;
use App\Services\Payment\Gateways\PayProGlobalPaymentGateway;
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    public function __construct(
        protected LicenseApiService $licenseApiService,
        protected FirstPromoterService $firstPromoterService
    ) {}

    public function create(string $gatewayName): PaymentGatewayInterface
    {
        $normalizedName = $this->normalizeGatewayName($gatewayName);

        return match ($normalizedName) {
            'fastspring' => new FastSpringPaymentGateway(
                $this->licenseApiService,
                $this->firstPromoterService
            ),
            'paddle' => new PaddlePaymentGateway(
                $this->licenseApiService,
                $this->firstPromoterService
            ),
            'payproglobal', 'pay pro global' => new PayProGlobalPaymentGateway(
                $this->licenseApiService,
                $this->firstPromoterService
            ),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gatewayName}")
        };
    }

    protected function normalizeGatewayName(string $gatewayName): string
    {
        return strtolower(trim($gatewayName));
    }

    public function createFromUser(\App\Models\User $user): ?PaymentGatewayInterface
    {
        if ($user->payment_gateway_id) {
            $paymentGateway = \App\Models\PaymentGateways::find($user->payment_gateway_id);
            if ($paymentGateway) {
                try {
                    return $this->create($paymentGateway->name);
                } catch (\InvalidArgumentException $e) {
                    Log::warning("Invalid payment gateway for user", [
                        'user_id' => $user->id,
                        'gateway_name' => $paymentGateway->name
                    ]);
                }
            }
        }

        $activeGateway = \App\Models\PaymentGateways::where('is_active', true)->first();
        if ($activeGateway) {
            try {
                return $this->create($activeGateway->name);
            } catch (\InvalidArgumentException $e) {
                Log::warning("Invalid active payment gateway", [
                    'gateway_name' => $activeGateway->name
                ]);
            }
        }

        return null;
    }

    public function detectGatewayFromUser(\App\Models\User $user, ?string $subscriptionId = null): ?\App\Models\PaymentGateways
    {
        if ($user->payment_gateway_id) {
            $gateway = $user->paymentGateway;
            if ($gateway) {
                return $gateway;
            }
        }

        $subscriptionId = $subscriptionId ?? $user->subscription_id ?? ($user->userLicence->subscription_id ?? null);

        if ($subscriptionId) {
            if (str_starts_with($subscriptionId, 'PADDLE-') || str_starts_with($subscriptionId, 'sub_')) {
                return \App\Models\PaymentGateways::where('name', 'Paddle')->first();
            } elseif (str_starts_with($subscriptionId, 'FS-') || str_starts_with($subscriptionId, 'fastspring_')) {
                return \App\Models\PaymentGateways::where('name', 'FastSpring')->first();
            } elseif (str_starts_with($subscriptionId, 'PPG-') || str_starts_with($subscriptionId, 'payproglobal_')) {
                return \App\Models\PaymentGateways::where('name', 'Pay Pro Global')->first();
            }
        }

        if ($user->paddle_customer_id) {
            return \App\Models\PaymentGateways::where('name', 'Paddle')->first();
        }

        return null;
    }
}
