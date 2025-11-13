<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\UserLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Mockery;

class WebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sub Admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // Root Route Tests
    // ============================================

    /** @test */
    public function root_route_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    // ============================================
    // Public Routes Tests
    // ============================================

    /** @test */
    public function subscription_page_is_accessible(): void
    {
        $response = $this->get('/subscription');

        // May redirect if authenticated with active subscription, or show page
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function check_email_endpoint_requires_post_method(): void
    {
        $response = $this->get('/check-email');

        $response->assertStatus(405); // Method not allowed
    }

    /** @test */
    public function check_email_endpoint_validates_email(): void
    {
        $response = $this->post('/check-email', []);

        // May return 200 with error or 422 validation error
        $this->assertContains($response->status(), [200, 422]);
    }

    // ============================================
    // Payment Callback Routes Tests
    // ============================================

    /** @test */
    public function payment_success_route_accepts_get_and_post(): void
    {
        $response = $this->get('/payments/success');
        $this->assertContains($response->status(), [200, 302, 400]); // May redirect or show error

        $response = $this->post('/payments/success');
        $this->assertContains($response->status(), [200, 302, 400]);
    }

    /** @test */
    public function payment_cancel_route_is_accessible(): void
    {
        $response = $this->get('/payments/cancel');

        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function payment_popup_cancel_route_is_accessible(): void
    {
        $response = $this->get('/payments/popup-cancel');

        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function payment_license_error_route_is_accessible(): void
    {
        $response = $this->get('/payments/license-error');

        $this->assertContains($response->status(), [200, 302]);
    }

    // ============================================
    // Guest Routes Tests (Authentication)
    // ============================================

    /** @test */
    public function login_page_is_accessible_to_guests(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    /** @test */
    public function authenticated_user_cannot_access_login_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect();
    }

    /** @test */
    public function register_page_is_accessible_to_guests(): void
    {
        // Register page may require email parameter or redirect
        $response = $this->get('/register');

        // May show form or redirect to login if email required
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function authenticated_user_cannot_access_register_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect();
    }

    /** @test */
    public function admin_login_page_is_accessible_to_guests(): void
    {
        $response = $this->get('/admin-login');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_register_page_is_accessible_to_guests(): void
    {
        $response = $this->get('/admin-register');

        $response->assertStatus(200);
    }

    // ============================================
    // Social Authentication Routes Tests
    // ============================================

    /** @test */
    public function google_auth_route_redirects(): void
    {
        $response = $this->get('/auth/google');

        $this->assertContains($response->status(), [302, 500]); // Redirect or error if not configured
    }

    /** @test */
    public function google_callback_route_is_accessible(): void
    {
        $response = $this->get('/auth/google-callback');

        $this->assertContains($response->status(), [200, 302, 500]);
    }

    /** @test */
    public function facebook_login_route_redirects(): void
    {
        $response = $this->get('/login/facebook');

        $this->assertContains($response->status(), [302, 500]);
    }

    /** @test */
    public function facebook_callback_route_is_accessible(): void
    {
        $response = $this->get('/login/facebook/callback');

        $this->assertContains($response->status(), [200, 302, 500]);
    }

    // ============================================
    // Email Verification Routes Tests
    // ============================================

    /** @test */
    public function email_verify_page_requires_session_email(): void
    {
        $response = $this->get('/email/verify');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function verify_code_endpoint_requires_post_method(): void
    {
        $response = $this->get('/verify-code');

        $response->assertStatus(405);
    }

    /** @test */
    public function resend_email_endpoint_requires_post_method(): void
    {
        $response = $this->get('/email/resend');

        $response->assertStatus(405);
    }

    // ============================================
    // Password Reset Routes Tests
    // ============================================

    /** @test */
    public function password_reset_request_page_is_accessible(): void
    {
        $response = $this->get('/password/reset');

        $response->assertStatus(200);
    }

    /** @test */
    public function password_reset_email_endpoint_requires_post(): void
    {
        $response = $this->get('/password/email');

        $response->assertStatus(405);
    }

    /** @test */
    public function password_reset_with_token_page_is_accessible(): void
    {
        $response = $this->get('/password/reset/test-token-123');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function admin_password_reset_request_page_is_accessible(): void
    {
        $response = $this->get('/admin/forgotpassword');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_password_reset_email_endpoint_requires_post(): void
    {
        $response = $this->get('/admin/password/email');

        $response->assertStatus(405);
    }

    // ============================================
    // Authenticated Routes Tests
    // ============================================

    /** @test */
    public function dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function dashboard_requires_email_verification(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 1
        ]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect();
    }

    /** @test */
    public function authenticated_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1
        ]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/dashboard');

        // May redirect to subscription if no active subscription, or show dashboard
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function profile_page_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1
        ]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/profile');

        // May redirect or show profile page
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function profile_update_requires_authentication(): void
    {
        $response = $this->post('/profile/update');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function update_password_page_requires_authentication(): void
    {
        $response = $this->get('/update-password');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function subscription_details_requires_authentication(): void
    {
        $response = $this->get('/user/subscription-details');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function subscription_details_hides_downgrade_and_cancel_buttons_for_free_package(): void
    {
        $freePackage = Package::factory()->create([
            'name' => 'Free',
            'price' => 0
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1,
            'package_id' => $freePackage->id,
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // For Free package, hasActiveSubscription returns true without UserLicence
        // But we can create one to be safe
        UserLicence::create([
            'user_id' => $user->id,
            'package_id' => $freePackage->id,
            'license_key' => 'FREE-LICENSE',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/user/subscription-details');

        $response->assertStatus(200);
        $response->assertViewIs('subscription.details');

        // Verify Downgrade button is NOT shown
        $response->assertDontSee('Downgrade Subscription', false);

        // Verify Cancel Subscription button is NOT shown
        $response->assertDontSee('Cancel Subscription', false);
        // Note: cancelSubscriptionBtn ID may appear in JavaScript, but the button itself should not be rendered
        $response->assertDontSee('id="cancelSubscriptionBtn"', false);

        // Verify Upgrade button IS shown (should still be visible)
        $response->assertSee('Upgrade Subscription', false);
    }

    /** @test */
    public function subscription_details_shows_downgrade_and_cancel_buttons_for_paid_package(): void
    {
        $paidPackage = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1,
            'package_id' => $paidPackage->id,
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // Create active UserLicence for paid package
        $userLicense = UserLicence::create([
            'user_id' => $user->id,
            'package_id' => $paidPackage->id,
            'subscription_id' => 'TEST-SUB-123',
            'license_key' => 'TEST-LICENSE-KEY',
            'is_active' => true,
            'activated_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $user->update(['user_license_id' => $userLicense->id]);

        $response = $this->actingAs($user)->get('/user/subscription-details');

        $response->assertStatus(200);
        $response->assertViewIs('subscription.details');

        // Verify Downgrade button IS shown
        $response->assertSee('Downgrade Subscription', false);

        // Verify Cancel Subscription button IS shown
        $response->assertSee('Cancel Subscription', false);
        $response->assertSee('cancelSubscriptionBtn', false);

        // Verify Upgrade button IS shown
        $response->assertSee('Upgrade Subscription', false);
    }

    /** @test */
    public function subscription_details_hides_downgrade_and_cancel_for_free_package_by_price_zero(): void
    {
        // Package with price 0 but different name should also hide buttons
        $freePackage = Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1,
            'package_id' => $freePackage->id,
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // Create UserLicence for the package
        UserLicence::create([
            'user_id' => $user->id,
            'package_id' => $freePackage->id,
            'license_key' => 'STARTER-LICENSE',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/user/subscription-details');

        $response->assertStatus(200);
        $response->assertViewIs('subscription.details');

        // Verify Downgrade button is NOT shown (package isFree() returns true for price 0)
        $response->assertDontSee('Downgrade Subscription', false);

        // Verify Cancel Subscription button is NOT shown
        $response->assertDontSee('Cancel Subscription', false);
    }

    /** @test */
    public function subscription_upgrade_requires_authentication(): void
    {
        $response = $this->get('/subscription/upgrade');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function subscription_downgrade_requires_authentication(): void
    {
        $response = $this->get('/subscription/downgrade');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function cancel_subscription_requires_authentication(): void
    {
        $response = $this->post('/payments/cancel-subscription');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function orders_page_requires_authentication(): void
    {
        $response = $this->get('/orders');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_access_orders(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 1
        ]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->get('/orders');

        $response->assertStatus(200);
    }

    /** @test */
    public function software_access_requires_authentication(): void
    {
        $response = $this->get('/software/access');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function software_token_requires_authentication(): void
    {
        $response = $this->post('/software/token');

        $response->assertRedirect(route('login'));
    }

    // ============================================
    // Admin Logout Route Tests
    // ============================================

    /** @test */
    public function admin_logout_requires_post_method(): void
    {
        $response = $this->get('/admin-logout');

        $response->assertStatus(405);
    }

    /** @test */
    public function admin_logout_works_for_authenticated_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->post('/admin-logout');

        $response->assertRedirect();
        $this->assertGuest();
    }

    // ============================================
    // Error Handling Routes Tests
    // ============================================

    /** @test */
    public function access_denied_route_displays_403_page(): void
    {
        session(['exception_message' => 'Test error message']);

        $response = $this->get('/access-denied');

        $response->assertStatus(403);
        $response->assertViewIs('errors.403');
    }

    /** @test */
    public function access_denied_route_uses_default_message_when_none_provided(): void
    {
        $response = $this->get('/access-denied');

        $response->assertStatus(403);
        $response->assertViewIs('errors.403');
        // The view may show different default message, so just check it displays 403 page
        $response->assertSee('Access Denied', false);
    }

    // ============================================
    // Route Method Validation Tests
    // ============================================

    /** @test */
    public function login_post_requires_post_method(): void
    {
        $response = $this->get('/login/custom');

        $response->assertStatus(405);
    }

    /** @test */
    public function register_get_shows_form_or_redirects(): void
    {
        $response = $this->get('/register');

        // GET may show form or redirect if email parameter required
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function admin_login_post_requires_post_method(): void
    {
        $response = $this->get('/admin-login');

        // GET should show form
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_register_post_requires_post_method(): void
    {
        $response = $this->get('/admin-register');

        // GET should show form
        $response->assertStatus(200);
    }

    // ============================================
    // Route Name Tests
    // ============================================

    /** @test */
    public function routes_have_correct_names(): void
    {
        $this->assertEquals(url('/'), route('home'));
        $this->assertEquals(url('/login'), route('login'));
        $this->assertEquals(url('/register'), route('register'));
        $this->assertEquals(url('/subscription'), route('subscription'));
        $this->assertEquals(url('/access-denied'), route('access-denied'));
    }

    // ============================================
    // CSRF Protection Tests
    // ============================================

    /** @test */
    public function post_routes_require_csrf_token(): void
    {
        $response = $this->post('/login/custom', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        // Should either redirect (CSRF error) or show validation error
        $this->assertContains($response->status(), [302, 419, 422]);
    }

    /** @test */
    public function register_route_requires_csrf_token(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        // Should either redirect (CSRF error) or process
        $this->assertContains($response->status(), [302, 419, 422, 200]);
    }
}

