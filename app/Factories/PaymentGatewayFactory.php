<?php

namespace App\Factories;
use App\Services\Payment\Gateways\FastSpringPaymentGateway;
use App\Services\Payment\Gateways\PaddlePaymentGateway;
use App\Services\Payment\Gateways\PayProGlobalPaymentGateway;
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;
use App\Services\TenantAssignmentService;
use App\Services\PasswordBindingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentGatewayFactory
{
    public function __construct(
        protected LicenseApiService $licenseApiService,
        protected FirstPromoterService $firstPromoterService,
        protected TenantAssignmentService $tenantAssignmentService,
        protected PasswordBindingService $passwordBindingService,
    ) {
    }

    public function create(string $gatewayName)
    {
        $normalizedName = Str::lower($gatewayName);

        return match ($normalizedName) {
            'fastspring' => new FastSpringPaymentGateway(
                $this->licenseApiService,
                $this->tenantAssignmentService
            ),
            'paddle' => new PaddlePaymentGateway(
                $this->licenseApiService,
                $this->firstPromoterService,
                $this->tenantAssignmentService
            ),
            'payproglobal', 'pay pro global' => new PayProGlobalPaymentGateway(
                $this->licenseApiService,
                $this->firstPromoterService,
                $this->tenantAssignmentService
            ),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gatewayName}")
        };
    }
}
