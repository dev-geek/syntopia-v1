<?php

namespace Tests\Feature;

use App\Services\Payment\Gateways\PaddlePaymentGateway;
use App\Services\License\LicenseApiService;
use App\Services\FirstPromoterService;
use App\Services\TenantAssignmentService;
use App\Services\Payment\PackageGatewayService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Mockery;

class PaddlePaymentGatewayTest extends PaymentGatewayTestBase
{
    protected $packageGatewayService;
    protected $firstPromoterService;

    protected function getGatewayName(): string
    {
        return 'Paddle';
    }

    protected function getGatewayClass(): string
    {
        return PaddlePaymentGateway::class;
    }

    protected function getSubscriptionId(): string
    {
        return 'sub_12345678901234567890123456';
    }

    protected function getTransactionIdPrefix(): string
    {
        return 'txn';
    }

    protected function getCheckoutUrlContains(): string
    {
        return 'checkout';
    }

    protected function getSuccessCallbackTransactionKey(): string
    {
        return 'transaction_id';
    }

    protected function getStarterPackagePrice(): float
    {
        return 39.00;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->user->update(['paddle_customer_id' => 'ctm_123456']);
    }

    protected function createGatewayInstance()
    {
        return new PaddlePaymentGateway(
            $this->licenseApiService,
            $this->firstPromoterService,
            $this->tenantAssignmentService,
            $this->packageGatewayService
        );
    }

    public function test_upgrade_creates_checkout_url_immediately()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update(['user_license_id' => $activeLicense->id]);

        $this->licenseApiService->shouldReceive('resolvePlanLicense')
            ->once()
            ->with('tenant-123', 'Pro')
            ->andReturn(['planId' => 'pro-plan-123']);

        $this->packageGatewayService->shouldReceive('getPaddlePriceId')
            ->once()
            ->andReturn('pri_123456');

        Http::fake([
            'api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_789',
                    'checkout' => [
                        'url' => 'https://checkout.paddle.com/test'
                    ],
                    'status' => 'pending'
                ]
            ], 200)
        ]);

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
        $this->assertStringContainsString($this->getCheckoutUrlContains(), $result['checkout_url']);

        $order->refresh();
        $this->assertEquals('upgrade', $order->metadata['action'] ?? null);
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

        $this->packageGatewayService->shouldReceive('getPaddlePriceId')
            ->once()
            ->andReturn('pri_123456');

        Http::fake([
            'api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_789',
                    'checkout' => [
                        'url' => 'https://checkout.paddle.com/test'
                    ],
                    'status' => 'pending'
                ]
            ], 200)
        ]);

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

