<?php

namespace Tests\Feature;

use App\Services\Payment\Gateways\PayProGlobalPaymentGateway;
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;
use App\Services\TenantAssignmentService;
use App\Services\Payment\PackageGatewayService;
use App\Models\Package;
use App\Models\Order;
use Mockery;

class PayProGlobalPaymentGatewayTest extends PaymentGatewayTestBase
{
    protected $packageGatewayService;
    protected $firstPromoterService;

    protected function getGatewayName(): string
    {
        return 'Pay Pro Global';
    }

    protected function getGatewayClass(): string
    {
        return PayProGlobalPaymentGateway::class;
    }

    protected function getSubscriptionId(): string
    {
        return '12345';
    }

    protected function getTransactionIdPrefix(): string
    {
        return 'PPG';
    }

    protected function getCheckoutUrlContains(): string
    {
        return 'payproglobal';
    }

    protected function getSuccessCallbackTransactionKey(): string
    {
        return 'ORDER_ID';
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
        $this->packageGatewayService = Mockery::mock(PackageGatewayService::class);
        $this->firstPromoterService = Mockery::mock(FirstPromoterService::class);

        $this->app->instance(PackageGatewayService::class, $this->packageGatewayService);
        $this->app->instance(FirstPromoterService::class, $this->firstPromoterService);
    }

    protected function createGatewayInstance()
    {
        return new PayProGlobalPaymentGateway(
            $this->licenseApiService,
            $this->firstPromoterService,
            $this->tenantAssignmentService,
            $this->packageGatewayService
        );
    }

    protected function getGatewayNameForCallback(): string
    {
        return 'payproglobal';
    }

    public function test_upgrade_creates_checkout_url_immediately()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update(['user_license_id' => $activeLicense->id]);

        $this->packageGatewayService->shouldReceive('getGatewayProductId')
            ->once()
            ->with(\Mockery::type(Package::class), 'PayProGlobal')
            ->andReturn(12345);

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'amount' => $this->proPackage->price,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->gateway->id,
            'transaction_id' => 'PPG-ORDER-123'
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
        $this->assertStringContainsString($this->getCheckoutUrlContains(), $result['checkout_url']);

        $order->refresh();
        $this->assertEquals('upgrade', $order->metadata['action'] ?? null);
    }

    public function test_downgrade_schedules_without_checkout()
    {
        $activeLicense = $this->createActiveLicense($this->proPackage->id);
        $this->user->update([
            'package_id' => $this->proPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $this->packageGatewayService->shouldReceive('getGatewayProductId')
            ->once()
            ->with(\Mockery::type(Package::class), 'PayProGlobal')
            ->andReturn(12345);

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->starterPackage->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'downgrade',
            'payment_gateway_id' => $this->gateway->id
        ]);

        $gateway = $this->createGatewayInstance();
        $gateway->setUser($this->user)->setOrder($order);

        $result = $gateway->handleDowngrade([
            'package' => 'Starter',
            'user' => $this->user
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('checkout_url', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('effective_date', $result);
        $this->assertTrue($result['applies_at_period_end'] ?? false);

        $scheduledOrder = Order::where('user_id', $this->user->id)
            ->where('order_type', 'downgrade')
            ->where('status', 'scheduled_downgrade')
            ->first();

        $this->assertNotNull($scheduledOrder);
        $this->assertEquals(0, $scheduledOrder->amount);
        $this->assertNull($scheduledOrder->transaction_id);
        $this->assertEquals($this->starterPackage->id, $scheduledOrder->package_id);
    }

    public function test_downgrade_requires_lower_price()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update([
            'package_id' => $this->starterPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $this->packageGatewayService->shouldReceive('getGatewayProductId')
            ->once()
            ->with(\Mockery::type(Package::class), 'PayProGlobal')
            ->andReturn(12345);

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'amount' => $this->proPackage->price,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->gateway->id,
            'transaction_id' => 'PPG-ORDER-123'
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

