<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use App\Services\LicenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Mockery;

class FreePackageAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Http::preventStrayRequests();
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
    public function paddle_checkout_assigns_free_package_instantly_without_payment_gateway()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 100
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/free");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Free plan activated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'redirect_url'
            ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(0, $order->amount);
        $this->assertStringStartsWith('FREE-', $order->transaction_id);

        $license = UserLicence::where('user_id', $user->id)->first();
        $this->assertNotNull($license);
        $this->assertEquals($freePackage->id, $license->package_id);
        $this->assertTrue($license->is_active);
        $this->assertNull($license->expires_at);
        $this->assertStringStartsWith('FREE-', $license->subscription_id);

        Http::assertNothingSent();
    }

    /** @test */
    public function fastspring_checkout_assigns_free_package_instantly_without_payment_gateway()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 200
        ]);

        PaymentGateways::create([
            'name' => 'FastSpring',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/fastspring/checkout/free");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Free plan activated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'redirect_url'
            ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(0, $order->amount);

        $license = UserLicence::where('user_id', $user->id)->first();
        $this->assertNotNull($license);
        $this->assertEquals($freePackage->id, $license->package_id);
        $this->assertTrue($license->is_active);

        Http::assertNothingSent();
    }

    /** @test */
    public function payproglobal_checkout_assigns_free_package_instantly_without_payment_gateway()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 300
        ]);

        PaymentGateways::create([
            'name' => 'Pay Pro Global',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/payproglobal/checkout/free");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Free plan activated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'redirect_url'
            ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(0, $order->amount);

        $license = UserLicence::where('user_id', $user->id)->first();
        $this->assertNotNull($license);
        $this->assertEquals($freePackage->id, $license->package_id);
        $this->assertTrue($license->is_active);

        Http::assertNothingSent();
    }

    /** @test */
    public function free_package_by_zero_price_is_assigned_instantly()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/starter");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Free plan activated successfully'
            ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(0, $order->amount);

        Http::assertNothingSent();
    }

    /** @test */
    public function free_package_checkout_does_not_require_payment_gateway()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 100
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/free");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);

        Http::assertNothingSent();
    }

    /** @test */
    public function paid_package_still_redirects_to_payment_gateway()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $proPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        Http::fake([
            'sandbox-api.paddle.com/*' => Http::response([
                'data' => [
                    'url' => 'https://checkout.paddle.com/test'
                ]
            ], 200)
        ]);

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/pro");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'checkout_url'
            ]);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $proPackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(99.99, $order->amount);

        $user->refresh();
        $this->assertNotEquals($proPackage->id, $user->package_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'paddle.com');
        });
    }

    /** @test */
    public function free_package_creates_license_without_expiration()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 100
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/free");

        $response->assertStatus(200);

        $license = UserLicence::where('user_id', $user->id)->first();
        $this->assertNotNull($license);
        $this->assertNull($license->expires_at);
        $this->assertTrue($license->is_active);
        $this->assertEquals('PKG-CL-FREE-01', $license->license_key);
    }

    /** @test */
    public function free_package_order_has_correct_metadata()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 100
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/free");

        $response->assertStatus(200);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('Free', $order->metadata['package']);
        $this->assertEquals('free_plan_assignment', $order->metadata['action']);
    }

    /** @test */
    public function free_package_checkout_blocks_user_who_already_used_free_plan()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'has_used_free_plan' => true
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 100
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        Http::fake();

        $response = $this->actingAs($user)->postJson("/api/payments/paddle/checkout/free");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'FREE_PLAN_ALREADY_USED',
                'reason' => 'already_used'
            ])
            ->assertJsonFragment([
                'message' => 'You have exceeded your limit to use the Free plan. Please buy a plan.'
            ]);

        $user->refresh();
        $this->assertNotEquals($freePackage->id, $user->package_id);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNull($order);

        Http::assertNothingSent();
    }

}

