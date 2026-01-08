<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Services\PaymentService;
use App\Services\License\LicenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Mockery;

class PackagePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Http::preventStrayRequests();

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockLicenseApiService(): void
    {
        $licenseApiService = Mockery::mock(LicenseApiService::class);

        $licenseApiService->shouldReceive('getSubscriptionSummary')
            ->andReturn([
                [
                    'subscriptionCode' => 'PKG-CL-FREE-01',
                    'subscriptionName' => 'Free Plan',
                    'remaining' => 10,
                    'total' => 10,
                    'used' => 0
                ]
            ]);

        $licenseApiService->shouldReceive('resolvePlanLicense')
            ->andReturn([
                'subscriptionCode' => 'PKG-CL-FREE-01',
                'subscriptionName' => 'Free Plan',
                'remaining' => 10
            ]);

        $licenseApiService->shouldReceive('addLicenseToTenant')
            ->andReturn(true);

        $this->app->instance(LicenseApiService::class, $licenseApiService);
    }

    /** @test */
    public function payment_service_charges_zero_for_free_package_by_name()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 50
        ]);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentSession('Free', $user);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(0, $order->amount);
        $this->assertEquals('Free', $order->package->name);
    }

    /** @test */
    public function payment_service_charges_zero_for_free_package_by_price()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentSession('Starter', $user);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(0, $order->amount);
    }

    /** @test */
    public function payment_service_charges_actual_price_for_paid_package()
    {
        $user = User::factory()->create();
        $proPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentSession('Pro', $user);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(99.99, $order->amount);
    }

    /** @test */
    public function free_package_always_charges_zero_regardless_of_price_field()
    {
        $user = User::factory()->create();

        $freePackageWithPrice = Package::factory()->create([
            'name' => 'Free',
            'price' => 999.99
        ]);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentSession('Free', $user);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(0, $order->amount);
        $this->assertNotEquals(999.99, $order->amount);
    }

    /** @test */
    public function free_package_case_insensitive_charges_zero()
    {
        $user = User::factory()->create();

        $freePackage = Package::factory()->create([
            'name' => 'free',
            'price' => 100
        ]);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentSession('free', $user);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(0, $order->amount);
    }

    /** @test */
    public function paddle_checkout_charges_zero_for_free_package()
    {
        $this->assertFreePackageCheckout('Paddle', 'paddle', [
            'sandbox-api.paddle.com/*' => Http::response(['data' => ['url' => 'https://checkout.paddle.com/test']], 200)
        ]);
    }

    /** @test */
    public function fastspring_checkout_charges_zero_for_free_package()
    {
        $this->assertFreePackageCheckout('FastSpring', 'fastspring');
    }

    /** @test */
    public function payproglobal_checkout_charges_zero_for_free_package()
    {
        $this->assertFreePackageCheckout('Pay Pro Global', 'payproglobal', [
            '*.payproglobal.com/*' => Http::response(['checkout_url' => 'https://checkout.payproglobal.com/test'], 200)
        ]);
    }

    protected function assertFreePackageCheckout(string $gatewayName, string $gatewaySlug, array $httpFake = []): void
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 999.99
        ]);

        PaymentGateways::create([
            'name' => $gatewayName,
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        if (!empty($httpFake)) {
            Http::fake($httpFake);
        }

        $this->actingAs($user)->postJson("/api/payments/{$gatewaySlug}/checkout/free");

        $order = Order::where('user_id', $user->id)->where('package_id', $freePackage->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(0, $order->amount);
        $this->assertNotEquals(999.99, $order->amount);
    }
}

