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
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;

class PaddlePaymentGateway implements PaymentGatewayInterface
{
    private ?User $user = null;

    private ?Order $order = null;

    public function __construct(
        private LicenseApiService $licenseApiService,
        private FirstPromoterService $firstPromoterService,
    ) {
    }

    public function setUser(User $user): PaymentGatewayInterface
    {
        $this->user = $user;

        return $this;
    }

    public function setOrder(Order $order): PaymentGatewayInterface
    {
        $this->order = $order;

        return $this;
    }

    public function processPayment(array $paymentData, bool $returnRedirect = true): array
    {
        // TODO: implement Paddle payment logic
        return [
            'success' => false,
            'error'   => 'Paddle payment processing not implemented yet',
        ];
    }
}
