<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use App\Services\License\LicenseApiService;
use App\Services\TenantAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

abstract class PaymentGatewayTestBase extends TestCase
{
    use RefreshDatabase;

    protected $starterPackage;
    protected $proPackage;
    protected $gateway;
    protected $user;
    protected $licenseApiService;
    protected $tenantAssignmentService;

    abstract protected function getGatewayName(): string;
    abstract protected function getGatewayClass(): string;
    abstract protected function getSubscriptionId(): string;
    abstract protected function getTransactionIdPrefix(): string;
    abstract protected function getCheckoutUrlContains(): string;
    abstract protected function getSuccessCallbackTransactionKey(): string;
    abstract protected function createGatewayInstance();
    abstract protected function setupGatewaySpecificMocks(): void;
    abstract protected function getStarterPackagePrice(): float;
    abstract protected function getProPackagePrice(): float;

    protected function getGatewayNameForCallback(): string
    {
        return strtolower($this->getGatewayName());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->starterPackage = Package::create([
            'name' => 'Starter',
            'price' => $this->getStarterPackagePrice(),
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        $this->proPackage = Package::create([
            'name' => 'Pro',
            'price' => $this->getProPackagePrice(),
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2', 'feature3'])
        ]);

        $this->gateway = PaymentGateways::create([
            'name' => $this->getGatewayName(),
            'is_active' => true
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => $this->getSubscriptionId(),
            'payment_gateway_id' => $this->gateway->id,
            'tenant_id' => 'tenant-123'
        ]);

        $this->licenseApiService = Mockery::mock(LicenseApiService::class);
        $this->tenantAssignmentService = Mockery::mock(TenantAssignmentService::class);

        $this->app->instance(LicenseApiService::class, $this->licenseApiService);
        $this->app->instance(TenantAssignmentService::class, $this->tenantAssignmentService);

        $this->setupGatewaySpecificMocks();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createActiveLicense(int $packageId, ?\Carbon\Carbon $expiresAt = null): UserLicence
    {
        return UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $packageId,
            'subscription_id' => $this->getSubscriptionId(),
            'license_key' => 'test-license-key',
            'activated_at' => now(),
            'expires_at' => $expiresAt ?? now()->addMonth(),
            'is_active' => true,
            'status' => 'active'
        ]);
    }

    protected function createExpiredLicense(int $packageId): UserLicence
    {
        return UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $packageId,
            'subscription_id' => $this->getSubscriptionId(),
            'license_key' => 'test-license-key',
            'activated_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(),
            'is_active' => true,
            'status' => 'active'
        ]);
    }

    public function test_upgrade_creates_checkout_url_immediately()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update(['user_license_id' => $activeLicense->id]);

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
        $this->assertStringContainsString($this->getCheckoutUrlContains(), $result['checkout_url']);

        $order->refresh();
        $this->assertEquals('upgrade', $order->metadata['action'] ?? null);
    }

    public function test_upgrade_instantly_upgrades_after_payment()
    {
        $activeLicense = $this->createActiveLicense($this->starterPackage->id);
        $this->user->update(['user_license_id' => $activeLicense->id]);

        $transactionId = $this->getTransactionIdPrefix() . '-txn-789';
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'amount' => $this->proPackage->price,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->gateway->id,
            'transaction_id' => $transactionId
        ]);

        $this->licenseApiService->shouldReceive('createAndActivateLicense')
            ->once()
            ->andReturn($activeLicense);

        $paymentService = app(\App\Services\Payment\PaymentService::class);
        $payload = [
            $this->getSuccessCallbackTransactionKey() => $transactionId,
            'package_name' => 'Pro'
        ];
        $result = $paymentService->processSuccessCallback($this->getGatewayNameForCallback(), $payload);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_upgrade'] ?? false);

        $this->user->refresh();
        $this->assertEquals($this->proPackage->id, $this->user->package_id);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    public function test_downgrade_schedules_without_checkout()
    {
        $activeLicense = $this->createActiveLicense($this->proPackage->id);
        $this->user->update([
            'package_id' => $this->proPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $this->licenseApiService->shouldReceive('resolvePlanLicense')
            ->once()
            ->with('tenant-123', 'Starter')
            ->andReturn(['planId' => 'starter-plan-123']);

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

    public function test_downgrade_message_displayed_on_subscription_details()
    {
        $activeLicense = $this->createActiveLicense($this->proPackage->id);
        $this->user->update([
            'package_id' => $this->proPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $scheduledOrder = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->starterPackage->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'scheduled_downgrade',
            'order_type' => 'downgrade',
            'payment_gateway_id' => $this->gateway->id,
            'metadata' => [
                'target_package_name' => 'Starter',
                'target_package_price' => $this->getStarterPackagePrice(),
                'scheduled_activation_date' => now()->addMonth()->toDateTimeString(),
                'applies_at_period_end' => true
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('user.subscription.details'));

        $response->assertStatus(200);
        $response->assertSee('Downgrade Scheduled', false);
        $response->assertSee('Starter', false);
    }

    public function test_downgrade_activates_when_package_expires()
    {
        $expiredLicense = $this->createExpiredLicense($this->proPackage->id);
        $this->user->update([
            'package_id' => $this->proPackage->id,
            'user_license_id' => $expiredLicense->id
        ]);

        $transactionId = $this->getTransactionIdPrefix() . '-downgrade-txn-123';
        $scheduledOrder = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->starterPackage->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'scheduled_downgrade',
            'order_type' => 'downgrade',
            'payment_gateway_id' => $this->gateway->id,
            'metadata' => [
                'target_package_name' => 'Starter',
                'target_package_price' => $this->getStarterPackagePrice(),
                'scheduled_activation_date' => now()->subDay()->toDateTimeString(),
                'applies_at_period_end' => true
            ]
        ]);

        $this->licenseApiService->shouldReceive('createAndActivateLicense')
            ->once()
            ->andReturn($expiredLicense);

        $paymentService = app(\App\Services\Payment\PaymentService::class);
        $payload = [
            $this->getSuccessCallbackTransactionKey() => $transactionId,
            'package_name' => 'Starter'
        ];
        $result = $paymentService->processSuccessCallback($this->getGatewayNameForCallback(), $payload);

        $scheduledOrder->refresh();
        $this->assertEquals('completed', $scheduledOrder->status);
        $this->assertEquals($this->getStarterPackagePrice(), $scheduledOrder->amount);
        $this->assertEquals($transactionId, $scheduledOrder->transaction_id);

        $this->user->refresh();
        $this->assertEquals($this->starterPackage->id, $this->user->package_id);
    }

    public function test_upgrade_requires_higher_price()
    {
        $activeLicense = $this->createActiveLicense($this->proPackage->id);
        $this->user->update([
            'package_id' => $this->proPackage->id,
            'user_license_id' => $activeLicense->id
        ]);

        $paymentService = app(\App\Services\Payment\PaymentService::class);
        $result = $paymentService->processPayment([
            'package' => 'Starter',
            'user' => $this->user
        ], $this->getGatewayName());

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('checkout_url', $result);
        $this->assertArrayHasKey('message', $result);
    }
}

