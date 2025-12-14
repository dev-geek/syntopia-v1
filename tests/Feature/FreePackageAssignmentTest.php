<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use App\Services\License\LicenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Mockery;

class FreePackageAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Http::preventStrayRequests();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
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
            'tenant_id' => 'test-tenant-123',
            'paddle_customer_id' => 'ctm_test123'
        ]);

        $proPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        // Mock LicenseApiService to return available licenses for Pro plan
        $licenseApiService = Mockery::mock(LicenseApiService::class);
        $licenseApiService->shouldReceive('resolvePlanLicense')
            ->andReturn([
                'subscriptionCode' => 'PKG-CL-OVS-03',
                'subscriptionName' => 'Pro Plan',
                'remaining' => 10,
                'total' => 10,
                'used' => 0
            ]);
        $this->app->instance(LicenseApiService::class, $licenseApiService);

        Http::fake([
            'sandbox-api.paddle.com/products*' => Http::response([
                'data' => [
                    [
                        'id' => 'prod_test123',
                        'name' => 'pro',
                        'prices' => [
                            [
                                'id' => 'pri_test123',
                                'status' => 'active'
                            ]
                        ]
                    ]
                ]
            ], 200),
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_test123',
                    'checkout' => [
                        'url' => 'https://checkout.paddle.com/test'
                    ]
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
                'message' => 'You have already used the free plan. Please buy a plan.'
            ]);

        $user->refresh();
        $this->assertNotEquals($freePackage->id, $user->package_id);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNull($order);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_with_free_package_name_assigns_free_package_automatically()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=free');

        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('success', 'Free plan has been assigned successfully!');

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);
        $this->assertTrue($user->has_used_free_plan);
        $this->assertNotNull($user->free_plan_used_at);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(0, $order->amount);

        // Note: FreePlanAbuseService::assignFreePlan() doesn't create licenses,
        // only orders. Licenses are created via LicenseApiService in payment flows.

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_with_free_package_name_redirects_to_login_if_not_authenticated()
    {
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $response = $this->get('/subscription?package_name=free');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('info', 'Please log in to get your free plan.');
        $this->assertEquals(url('/subscription?package_name=free'), session('url.intended'));
    }

    /** @test */
    public function subscription_route_with_free_package_name_prevents_duplicate_free_plan_assignment()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
            'has_used_free_plan' => true,
        ]);
        $user->assignRole('User');

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=free');

        $response->assertRedirect(route('subscription'));
        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('already used', $errorMessage);

        $user->refresh();
        // User should not have the free package assigned
        $this->assertNotEquals($freePackage->id, $user->package_id ?? null);

        $order = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->where('status', 'completed')
            ->first();

        // Should not create a new order
        $this->assertNull($order);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_prevents_free_plan_if_user_currently_has_free_package()
    {
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
            'package_id' => $freePackage->id,
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=free');

        $response->assertRedirect(route('subscription'));
        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('already used', $errorMessage);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_prevents_free_plan_if_user_has_previous_free_package_order()
    {
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        // Create a previous completed order for free package
        Order::create([
            'user_id' => $user->id,
            'package_id' => $freePackage->id,
            'amount' => 0,
            'status' => 'completed',
            'transaction_id' => 'FREE-PREVIOUS-123',
        ]);

        $this->mockLicenseApiService();
        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=free');

        $response->assertRedirect(route('subscription'));
        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('already used', $errorMessage);

        // Should not create a new order
        $newOrder = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->where('transaction_id', '!=', 'FREE-PREVIOUS-123')
            ->first();

        $this->assertNull($newOrder);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_prevents_free_plan_if_user_has_zero_amount_completed_order()
    {
        $paidPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        // Create a zero-amount completed order (considered as free usage)
        Order::create([
            'user_id' => $user->id,
            'package_id' => $paidPackage->id,
            'amount' => 0,
            'status' => 'completed',
            'transaction_id' => 'ZERO-AMOUNT-123',
        ]);

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=free');

        $response->assertRedirect(route('subscription'));
        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('already used', $errorMessage);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_allows_free_plan_only_once_per_user()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $this->mockLicenseApiService();
        Http::fake();

        // First attempt - should succeed
        $response1 = $this->actingAs($user)->get('/subscription?package_name=free');

        $response1->assertRedirect(route('user.dashboard'));
        $response1->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->has_used_free_plan);

        // Second attempt - should fail
        $response2 = $this->actingAs($user)->get('/subscription?package_name=free');

        $response2->assertRedirect(route('subscription'));
        $response2->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertNotNull($errorMessage);
        $this->assertStringContainsString('already used', $errorMessage);

        // Verify only one order was created
        $orders = Order::where('user_id', $user->id)
            ->where('package_id', $freePackage->id)
            ->where('status', 'completed')
            ->get();

        $this->assertCount(1, $orders);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_with_free_package_name_works_case_insensitive()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $this->mockLicenseApiService();

        Http::fake();

        // Test with uppercase
        $response = $this->actingAs($user)->get('/subscription?package_name=FREE');

        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_with_free_package_name_works_with_package_isFree_method()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        // Package with price 0 (not named "Free") should also trigger free plan assignment
        // Note: FreePlanAbuseService always assigns the "Free" package, not the original package
        Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        Http::fake();

        $response = $this->actingAs($user)->get('/subscription?package_name=Starter');

        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();

        // FreePlanAbuseService::assignFreePlan() always creates/finds a "Free" package
        $freePackage = Package::where('name', 'Free')->first();
        $this->assertNotNull($freePackage);
        $this->assertEquals($freePackage->id, $user->package_id);

        Http::assertNothingSent();
    }

    /** @test */
    public function subscription_route_with_paid_package_name_shows_subscription_page()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $paidPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $response = $this->actingAs($user)->get('/subscription?package_name=Pro');

        // Should show subscription page, not redirect
        $response->assertStatus(200);
        $response->assertViewIs('subscription.index');

        $user->refresh();
        // User should not have the package assigned yet
        $this->assertNotEquals($paidPackage->id, $user->package_id ?? null);
    }

}

