<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\UserLicence;
use App\Services\PasswordBindingService;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Mockery;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Mail::fake();

        // Create roles
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sub Admin', 'guard_name' => 'web']);

        // Disable bypass in testing environment
        config(['free_plan_abuse.bypass_in_testing' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Mock Socialite Google user
     */
    protected function mockGoogleUser(string $id = 'google123', string $email = 'google@example.com', string $name = 'Google User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->avatar = 'https://example.com/avatar.jpg';
        return $user;
    }

    /**
     * Mock Socialite Facebook user
     */
    protected function mockFacebookUser(string $id = 'facebook123', string $email = 'facebook@example.com', string $name = 'Facebook User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->avatar = 'https://example.com/avatar.jpg';
        return $user;
    }

    /**
     * Mock PasswordBindingService for successful password binding
     */
    protected function mockPasswordBindingService(bool $success = true, string $tenantId = null): void
    {
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);

        if ($success) {
            // Mock bindPassword for existing users with tenant_id
            $passwordBindingService->shouldReceive('bindPassword')
                ->andReturn([
                    'success' => true,
                    'data' => null,
                    'error_message' => null
                ]);

            // Mock createTenantAndBindPassword for new users or users without tenant_id
            if ($tenantId) {
                $passwordBindingService->shouldReceive('createTenantAndBindPassword')
                    ->andReturn([
                        'success' => true,
                        'data' => [
                            'tenantId' => $tenantId
                        ],
                        'error_message' => null
                    ]);
            } else {
                $passwordBindingService->shouldReceive('createTenantAndBindPassword')
                    ->andReturn([
                        'success' => true,
                        'data' => [
                            'tenantId' => 'default-tenant-123'
                        ],
                        'error_message' => null
                    ]);
            }
        } else {
            $passwordBindingService->shouldReceive('bindPassword')
                ->andReturn([
                    'success' => false,
                    'data' => null,
                    'error_message' => 'Password binding failed'
                ]);

            $passwordBindingService->shouldReceive('createTenantAndBindPassword')
                ->andReturn([
                    'success' => false,
                    'data' => null,
                    'error_message' => 'Tenant creation failed'
                ]);
        }

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);
    }

    /**
     * Mock Socialite driver
     */
    protected function mockSocialite(string $driver, SocialiteUser $user): void
    {
        $socialiteMock = Mockery::mock('alias:' . \Laravel\Socialite\Contracts\Provider::class);

        Socialite::shouldReceive('driver')
            ->with($driver)
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'redirect' => redirect('/auth/google'),
                'user' => $user
            ]));
    }

    /**
     * Test Google login redirect
     */
    public function test_google_login_redirects_to_google(): void
    {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'redirect' => redirect('https://accounts.google.com/oauth')
            ]));

        $response = $this->get(route('auth.google'));

        $response->assertRedirect('https://accounts.google.com/oauth');
    }

    /**
     * Test Google authentication for existing user with google_id
     */
    public function test_google_authentication_logs_in_existing_user_with_google_id(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google123',
            'email' => 'google@example.com',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $googleUser = $this->mockGoogleUser('google123', 'google@example.com');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        $response = $this->get(route('auth.google-callback'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('subscription'));
    }

    /**
     * Test Google authentication creates new user with tenant_id
     */
    public function test_google_authentication_creates_new_user_with_tenant_id(): void
    {
        $tenantId = 'test-tenant-google';
        $googleUser = $this->mockGoogleUser('google456', 'newgoogle@example.com', 'New Google User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        // Mock HTTP for tenant creation
        Http::fake([
            '*/api/partner/tenant/create' => Http::response([
                'code' => 200,
                'data' => ['tenantId' => $tenantId],
                'message' => 'Success'
            ], 200),
            '*/api/partner/tenant/user/password/bind' => Http::response([
                'code' => 200,
                'data' => [],
                'message' => 'Success'
            ], 200),
        ]);

        $response = $this->get(route('auth.google-callback'));

        // User should be created
        $user = User::where('google_id', 'google456')->first();
        $this->assertNotNull($user);
        $this->assertEquals('newgoogle@example.com', $user->email);
        $this->assertEquals('New Google User', $user->name);
        $this->assertEquals($tenantId, $user->tenant_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals(1, $user->status);
        $this->assertTrue($user->hasRole('User'));

        // User should be authenticated
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /**
     * Test Google authentication links to existing user by email and creates tenant_id if missing
     */
    public function test_google_authentication_links_to_existing_user_by_email_and_creates_tenant_id(): void
    {
        $tenantId = 'test-tenant-google-link';
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
            'tenant_id' => null, // User doesn't have tenant_id
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $existingUser->assignRole('User');

        $googleUser = $this->mockGoogleUser('google789', 'existing@example.com', 'Existing User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        // Mock PasswordBindingService to create tenant (since user doesn't have tenant_id)
        $this->mockPasswordBindingService(true, $tenantId);

        $response = $this->get(route('auth.google-callback'));

        // User should be updated with google_id and tenant_id
        $existingUser->refresh();
        $this->assertEquals('google789', $existingUser->google_id);
        $this->assertEquals($tenantId, $existingUser->tenant_id);
        $this->assertNotNull($existingUser->subscriber_password);

        // User should be authenticated
        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect();
    }

    /**
     * Test Google authentication handles API failure gracefully
     */
    public function test_google_authentication_handles_api_failure_gracefully(): void
    {
        $googleUser = $this->mockGoogleUser('google999', 'apifail@example.com', 'API Fail User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        // Mock HTTP to return error
        Http::fake([
            '*/api/partner/tenant/create' => Http::response([
                'code' => 730,
                'message' => 'User is already registered in the system',
                'data' => null
            ], 200),
        ]);

        $response = $this->get(route('auth.google-callback'));

        // User should not be created
        $user = User::where('email', 'apifail@example.com')->first();
        $this->assertNull($user);

        // Should redirect to login with error
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('swal_error');
    }

    /**
     * Test Facebook login redirect
     */
    public function test_facebook_login_redirects_to_facebook(): void
    {
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'redirect' => redirect('https://www.facebook.com/oauth')
            ]));

        $response = $this->get(route('login.facebook'));

        $response->assertRedirect('https://www.facebook.com/oauth');
    }

    /**
     * Test Facebook authentication for existing user with facebook_id
     */
    public function test_facebook_authentication_logs_in_existing_user_with_facebook_id(): void
    {
        $user = User::factory()->create([
            'facebook_id' => 'facebook123',
            'email' => 'facebook@example.com',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $facebookUser = $this->mockFacebookUser('facebook123', 'facebook@example.com');

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $facebookUser
            ]));

        $response = $this->get(route('auth.facebook-callback'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /**
     * Test Facebook authentication creates new user with tenant_id
     */
    public function test_facebook_authentication_creates_new_user_with_tenant_id(): void
    {
        $tenantId = 'test-tenant-facebook';
        $facebookUser = $this->mockFacebookUser('facebook456', 'newfacebook@example.com', 'New Facebook User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $facebookUser
            ]));

        // Mock PasswordBindingService to return tenant_id
        $this->mockPasswordBindingService(true, $tenantId);

        $response = $this->get(route('auth.facebook-callback'));

        // User should be created with tenant_id
        $user = User::where('facebook_id', 'facebook456')->first();
        $this->assertNotNull($user);
        $this->assertEquals('newfacebook@example.com', $user->email);
        $this->assertEquals('New Facebook User', $user->name);
        $this->assertEquals($tenantId, $user->tenant_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals(1, $user->status);
        $this->assertTrue($user->hasRole('User'));
        $this->assertNotNull($user->subscriber_password);

        // User should be authenticated
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /**
     * Test Facebook authentication links to existing user by email and creates tenant_id if missing
     */
    public function test_facebook_authentication_links_to_existing_user_by_email_and_creates_tenant_id(): void
    {
        $tenantId = 'test-tenant-link';
        $existingUser = User::factory()->create([
            'email' => 'existingfb@example.com',
            'facebook_id' => null,
            'tenant_id' => null, // User doesn't have tenant_id
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $existingUser->assignRole('User');

        $facebookUser = $this->mockFacebookUser('facebook789', 'existingfb@example.com', 'Existing FB User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $facebookUser
            ]));

        // Mock PasswordBindingService to create tenant (since user doesn't have tenant_id)
        $this->mockPasswordBindingService(true, $tenantId);

        $response = $this->get(route('auth.facebook-callback'));

        // User should be updated with facebook_id and tenant_id
        $existingUser->refresh();
        $this->assertEquals('facebook789', $existingUser->facebook_id);
        $this->assertEquals($tenantId, $existingUser->tenant_id);
        $this->assertNotNull($existingUser->subscriber_password);

        // User should be authenticated
        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect();
    }

    /**
     * Test Facebook authentication handles tenant creation failure gracefully
     */
    public function test_facebook_authentication_handles_tenant_creation_failure(): void
    {
        $facebookUser = $this->mockFacebookUser('facebook999', 'fail@example.com', 'Fail User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $facebookUser
            ]));

        // Mock PasswordBindingService to fail tenant creation
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $passwordBindingService->shouldReceive('createTenantAndBindPassword')
            ->andReturn([
                'success' => false,
                'data' => null,
                'error_message' => 'Failed to create tenant'
            ]);

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);

        $response = $this->get(route('auth.facebook-callback'));

        // User should not be created (transaction rolled back)
        $user = User::where('email', 'fail@example.com')->first();
        $this->assertNull($user);

        // Should redirect to login with error
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    /**
     * Test Google authentication redirects Super Admin to admin dashboard
     */
    public function test_google_authentication_redirects_super_admin_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'google_id' => 'admin-google',
            'email' => 'admin@example.com',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Super Admin');

        $googleUser = $this->mockGoogleUser('admin-google', 'admin@example.com');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        $response = $this->get(route('auth.google-callback'));

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('login_success');
    }

    /**
     * Test Google authentication redirects user with subscription to dashboard
     */
    public function test_google_authentication_redirects_user_with_subscription_to_dashboard(): void
    {
        $package = Package::factory()->create(['name' => 'Starter', 'price' => 29.99]);
        $user = User::factory()->create([
            'google_id' => 'subscribed-google',
            'email' => 'subscribed@example.com',
            'status' => 1,
            'email_verified_at' => now(),
            'package_id' => $package->id,
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // Create active license for the user to have active subscription
        $license = UserLicence::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'license_key' => 'test-license-key',
            'subscription_id' => 'test-subscription',
            'is_active' => true,
            'activated_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Update user to reference the license
        $user->update(['user_license_id' => $license->id]);
        $user->refresh();

        $googleUser = $this->mockGoogleUser('subscribed-google', 'subscribed@example.com');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        $response = $this->get(route('auth.google-callback'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('user.dashboard'));
    }

    /**
     * Test Google authentication handles exception gracefully
     */
    public function test_google_authentication_handles_exception_gracefully(): void
    {
        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andThrow(new \Exception('Socialite error'));

        $response = $this->get(route('auth.google-callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('connecting to Google', session('error'));
    }

    /**
     * Test Facebook authentication handles exception gracefully
     */
    public function test_facebook_authentication_handles_exception_gracefully(): void
    {
        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andThrow(new \Exception('Socialite error'));

        $response = $this->get(route('auth.facebook-callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('connecting to Facebook', session('error'));
    }

    /**
     * Test Google authentication creates user with tenant_id and can purchase plan
     */
    public function test_google_authentication_creates_user_with_tenant_id_can_purchase_plan(): void
    {
        $tenantId = 'test-tenant-social';
        $googleUser = $this->mockGoogleUser('social123', 'social@example.com', 'Social User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        // Mock HTTP for tenant creation
        Http::fake([
            '*/api/partner/tenant/create' => Http::response([
                'code' => 200,
                'data' => ['tenantId' => $tenantId],
                'message' => 'Success'
            ], 200),
            '*/api/partner/tenant/user/password/bind' => Http::response([
                'code' => 200,
                'data' => [],
                'message' => 'Success'
            ], 200),
        ]);

        $response = $this->get(route('auth.google-callback'));

        // User should be created with tenant_id
        $user = User::where('google_id', 'social123')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenantId, $user->tenant_id);

        // User should be authenticated
        $this->assertAuthenticatedAs($user);

        // User should be able to access subscription page
        $subscriptionResponse = $this->actingAs($user)
            ->get(route('subscription'));

        $subscriptionResponse->assertStatus(200);
    }

    /**
     * Test Google authentication links to existing user with tenant_id only binds password
     */
    public function test_google_authentication_links_to_existing_user_with_tenant_id_only_binds_password(): void
    {
        $existingTenantId = 'existing-tenant-123';
        $existingUser = User::factory()->create([
            'email' => 'existing-tenant@example.com',
            'google_id' => null,
            'tenant_id' => $existingTenantId, // User already has tenant_id
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $existingUser->assignRole('User');

        $googleUser = $this->mockGoogleUser('google-with-tenant', 'existing-tenant@example.com', 'Existing User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $googleUser
            ]));

        // Mock PasswordBindingService - should only call bindPassword, not createTenantAndBindPassword
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $passwordBindingService->shouldReceive('bindPassword')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => null,
                'error_message' => null
            ]);
        $passwordBindingService->shouldNotReceive('createTenantAndBindPassword');

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);

        $response = $this->get(route('auth.google-callback'));

        // User should be updated with google_id but tenant_id should remain the same
        $existingUser->refresh();
        $this->assertEquals('google-with-tenant', $existingUser->google_id);
        $this->assertEquals($existingTenantId, $existingUser->tenant_id); // Should not change
        $this->assertNotNull($existingUser->subscriber_password);

        // User should be authenticated
        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect();
    }

    /**
     * Test Facebook authentication links to existing user with tenant_id only binds password
     */
    public function test_facebook_authentication_links_to_existing_user_with_tenant_id_only_binds_password(): void
    {
        $existingTenantId = 'existing-tenant-fb-123';
        $existingUser = User::factory()->create([
            'email' => 'existing-tenant-fb@example.com',
            'facebook_id' => null,
            'tenant_id' => $existingTenantId, // User already has tenant_id
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $existingUser->assignRole('User');

        $facebookUser = $this->mockFacebookUser('facebook-with-tenant', 'existing-tenant-fb@example.com', 'Existing FB User');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn(Mockery::mock(\Laravel\Socialite\Contracts\Provider::class, [
                'user' => $facebookUser
            ]));

        // Mock PasswordBindingService - should only call bindPassword, not createTenantAndBindPassword
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $passwordBindingService->shouldReceive('bindPassword')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => null,
                'error_message' => null
            ]);
        $passwordBindingService->shouldNotReceive('createTenantAndBindPassword');

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);

        $response = $this->get(route('auth.facebook-callback'));

        // User should be updated with facebook_id but tenant_id should remain the same
        $existingUser->refresh();
        $this->assertEquals('facebook-with-tenant', $existingUser->facebook_id);
        $this->assertEquals($existingTenantId, $existingUser->tenant_id); // Should not change
        $this->assertNotNull($existingUser->subscriber_password);

        // User should be authenticated
        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect();
    }
}

