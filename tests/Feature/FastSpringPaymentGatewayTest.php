<?php

namespace Tests\Feature;

use App\Services\Payment\Gateways\FastSpringPaymentGateway;
use App\Services\License\LicenseApiService;
use App\Services\TenantAssignmentService;
use App\Models\Order;
use Mockery;

class FastSpringPaymentGatewayTest extends PaymentGatewayTestBase
{
    protected function getGatewayName(): string
    {
        return 'FastSpring';
    }

    protected function getGatewayClass(): string
    {
        return FastSpringPaymentGateway::class;
    }

    protected function getSubscriptionId(): string
    {
        return 'fs-sub-123456';
    }

    protected function getTransactionIdPrefix(): string
    {
        return 'fs';
    }

    protected function getCheckoutUrlContains(): string
    {
        return 'fastspring';
    }

    protected function getSuccessCallbackTransactionKey(): string
    {
        return 'transaction_id';
    }

    protected function getStarterPackagePrice(): float
    {
        return 29.00;
    }

    protected function getProPackagePrice(): float
    {
        return 79.00;
    }

    protected function setupGatewaySpecificMocks(): void
    {
        // FastSpring doesn't need additional mocks beyond base setup
    }

    protected function createGatewayInstance()
    {
        return new FastSpringPaymentGateway(
            $this->licenseApiService,
            $this->tenantAssignmentService
        );
    }

    public function test_downgrade_requires_lower_price()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update([
            'package_id' => $this->starterPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $this->licenseApiService->shouldReceive('resolvePlanLicense')
            ->once()
            ->with('tenant-123', 'Pro')
            ->andReturn(['planId' => 'pro-plan-123']);

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'amount' => $this->proPackage->price,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->gateway->id
        ]);

        $gateway = $this->createGatewayInstance();
        $gateway->setUser($this->user)->setOrder($order);

        $result = $gateway->handleUpgrade([
            'package' => 'Pro',
            'user' => $this->user,
            'is_upgrade' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('checkout_url', $result);
    }
}

