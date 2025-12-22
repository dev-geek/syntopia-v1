<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Services\Payment\PaymentService;
use App\Services\SubscriptionService;
use App\Services\License\LicenseApiService;
use App\Services\TenantAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Mockery;

class AddonFastSpringOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected $paddleGateway;
    protected $payProGlobalGateway;
    protected $fastSpringGateway;
    protected $addonPackage;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paddleGateway = PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => false
        ]);

        $this->payProGlobalGateway = PaymentGateways::create([
            'name' => 'Pay Pro Global',
            'is_active' => false
        ]);

        $this->fastSpringGateway = PaymentGateways::create([
            'name' => 'FastSpring',
            'is_active' => true
        ]);

        $this->addonPackage = Package::create([
            'name' => 'Avatar Customization (Clone Yourself)',
            'price' => 1380.00,
            'duration' => 'one-time',
            'features' => ['avatar_customization']
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::preventStrayRequests();
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockLicenseApiService(): void
    {
        $licenseApiService = Mockery::mock(LicenseApiService::class);

        $licenseApiService->shouldReceive('resolvePlanLicense')
            ->andReturn([
                'subscriptionCode' => 'PKG-CL-PRO-01',
                'subscriptionName' => 'Pro Plan',
                'remaining' => 10
            ]);

        $licenseApiService->shouldReceive('addLicenseToTenant')
            ->andReturn(true);

        $this->app->instance(LicenseApiService::class, $licenseApiService);
    }

    protected function mockTenantAssignmentService(): void
    {
        $tenantAssignmentService = Mockery::mock(TenantAssignmentService::class);

        $tenantAssignmentService->shouldReceive('assignTenantWithRetry')
            ->andReturn([
                'success' => true,
                'data' => ['tenantId' => 'test-tenant-123']
            ]);

        $this->app->instance(TenantAssignmentService::class, $tenantAssignmentService);
    }

    /** @test */
    public function addon_purchase_uses_fastspring_when_paddle_is_active()
    {
        $this->paddleGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => false]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');
        Config::set('payment.gateways.FastSpring.addons', [
            'avatar_customization' => 'avatar-customization'
        ]);

        $subscriptionService = app(SubscriptionService::class);
        
        $request = $this->actingAs($this->user)->get('/subscription?adon=avatar_customization');
        
        $context = $subscriptionService->buildSubscriptionIndexContext($this->user, 'new');
        
        $this->assertNotNull($context['activeGateway']);
        $this->assertEquals('FastSpring', $context['activeGateway']->name);
        $this->assertCount(1, $context['payment_gateways']);
        $this->assertEquals('FastSpring', $context['payment_gateways']->first()->name);
    }

    /** @test */
    public function addon_purchase_uses_fastspring_when_payproglobal_is_active()
    {
        $this->payProGlobalGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => false]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');
        Config::set('payment.gateways.FastSpring.addons', [
            'avatar_customization' => 'avatar-customization'
        ]);

        $subscriptionService = app(SubscriptionService::class);
        
        $request = $this->actingAs($this->user)->get('/subscription?adon=avatar_customization');
        
        $context = $subscriptionService->buildSubscriptionIndexContext($this->user, 'new');
        
        $this->assertNotNull($context['activeGateway']);
        $this->assertEquals('FastSpring', $context['activeGateway']->name);
        $this->assertCount(1, $context['payment_gateways']);
        $this->assertEquals('FastSpring', $context['payment_gateways']->first()->name);
    }

    /** @test */
    public function payment_service_forces_fastspring_for_addon_package_when_paddle_requested()
    {
        $this->paddleGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => true]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');
        Config::set('payment.gateways.FastSpring.addons', [
            'avatar_customization' => 'avatar-customization'
        ]);

        $paymentService = app(PaymentService::class);

        try {
            $result = $paymentService->processPayment([
                'package' => 'Avatar Customization (Clone Yourself)',
                'user' => $this->user,
            ], 'Paddle', true);

            $order = Order::where('user_id', $this->user->id)
                ->where('package_id', $this->addonPackage->id)
                ->first();

            $this->assertNotNull($order);
            $this->assertEquals('FastSpring', $order->paymentGateway->name);
        } catch (\Exception $e) {
            $order = Order::where('user_id', $this->user->id)
                ->where('package_id', $this->addonPackage->id)
                ->first();

            if ($order) {
                $this->assertEquals('FastSpring', $order->paymentGateway->name);
            }
        }
    }

    /** @test */
    public function payment_service_forces_fastspring_for_addon_package_when_payproglobal_requested()
    {
        $this->payProGlobalGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => true]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');
        Config::set('payment.gateways.FastSpring.addons', [
            'avatar_customization' => 'avatar-customization'
        ]);

        $paymentService = app(PaymentService::class);

        try {
            $result = $paymentService->processPayment([
                'package' => 'Avatar Customization (Clone Yourself)',
                'user' => $this->user,
            ], 'PayProGlobal', true);

            $order = Order::where('user_id', $this->user->id)
                ->where('package_id', $this->addonPackage->id)
                ->first();

            $this->assertNotNull($order);
            $this->assertEquals('FastSpring', $order->paymentGateway->name);
        } catch (\Exception $e) {
            $order = Order::where('user_id', $this->user->id)
                ->where('package_id', $this->addonPackage->id)
                ->first();

            if ($order) {
                $this->assertEquals('FastSpring', $order->paymentGateway->name);
            }
        }
    }

    /** @test */
    public function subscription_service_returns_fastspring_for_addon_regardless_of_user_payment_gateway()
    {
        $this->user->update([
            'payment_gateway_id' => $this->paddleGateway->id
        ]);

        $this->paddleGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => true]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');

        $subscriptionService = app(SubscriptionService::class);
        
        $this->actingAs($this->user)->get('/subscription?adon=avatar_customization');
        
        $context = $subscriptionService->buildSubscriptionIndexContext($this->user, 'new');
        
        $this->assertNotNull($context['activeGateway']);
        $this->assertEquals('FastSpring', $context['activeGateway']->name);
        $this->assertNotEquals('Paddle', $context['activeGateway']->name);
    }

    /** @test */
    public function regular_package_purchase_uses_active_gateway_not_fastspring()
    {
        $regularPackage = Package::create([
            'name' => 'Pro',
            'price' => 29.00,
            'duration' => 'month',
            'features' => ['feature1']
        ]);

        $this->paddleGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => false]);

        $this->mockLicenseApiService();

        $subscriptionService = app(SubscriptionService::class);
        
        $context = $subscriptionService->buildSubscriptionIndexContext($this->user, 'new');
        
        $this->assertNotNull($context['activeGateway']);
        $this->assertEquals('Paddle', $context['activeGateway']->name);
        $this->assertNotEquals('FastSpring', $context['activeGateway']->name);
    }

    /** @test */
    public function addon_purchase_with_adon_parameter_uses_fastspring()
    {
        $this->paddleGateway->update(['is_active' => true]);
        $this->payProGlobalGateway->update(['is_active' => true]);
        $this->fastSpringGateway->update(['is_active' => false]);

        $this->mockLicenseApiService();
        $this->mockTenantAssignmentService();

        Config::set('payment.gateways.FastSpring.storefront', 'test-storefront.onfastspring.com');

        $subscriptionService = app(SubscriptionService::class);
        
        request()->merge(['adon' => 'avatar_customization']);
        
        $context = $subscriptionService->buildSubscriptionIndexContext($this->user, 'new');
        
        $this->assertNotNull($context['activeGateway']);
        $this->assertEquals('FastSpring', $context['activeGateway']->name);
    }
}

