<?php

namespace Tests\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Mockery;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sub Admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDeviceFingerprintService(): void
    {
        $deviceFingerprintService = Mockery::mock(DeviceFingerprintService::class);
        $deviceFingerprintService->shouldReceive('recordUserDeviceInfo')->andReturn(true);

        $this->app->instance(DeviceFingerprintService::class, $deviceFingerprintService);
    }

    public function test_user_can_view_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_user_can_login_with_valid_credentials_and_verified_email(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_email(): void
    {
        $response = $this->post('/login/custom', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_without_email(): void
    {
        $response = $this->post('/login/custom', [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_without_password(): void
    {
        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post('/login/custom', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('does not exist', $response->session()->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertStringContainsString('incorrect', $response->session()->get('errors')->first('password'));
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_unverified_email(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 0,
            'email_verified_at' => null,
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors();
        $this->assertStringContainsString('verify your email', $response->session()->get('errors')->first());
        $this->assertGuest();
    }

    public function test_user_cannot_login_when_status_is_zero(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 0,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_super_admin_can_login_and_redirects_to_admin_dashboard(): void
    {
        $this->mockDeviceFingerprintService();

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Super Admin');

        $response = $this->post('/login/custom', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_super_admin_email_redirects_to_admin_login_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Super Admin');

        $response = $this->post('/login/custom', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin-login'));
        $this->assertEquals('admin@example.com', session('email'));
    }

    public function test_sub_admin_can_login_when_active(): void
    {
        $this->mockDeviceFingerprintService();

        $subAdmin = User::factory()->create([
            'email' => 'subadmin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $subAdmin->assignRole('Sub Admin');

        $response = $this->post('/login/custom', [
            'email' => 'subadmin@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($subAdmin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_sub_admin_email_redirects_to_admin_login_page(): void
    {
        $subAdmin = User::factory()->create([
            'email' => 'subadmin@example.com',
            'password' => Hash::make('password'),
        ]);
        $subAdmin->assignRole('Sub Admin');

        $response = $this->post('/login/custom', [
            'email' => 'subadmin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin-login'));
        $this->assertEquals('subadmin@example.com', session('email'));
    }

    public function test_user_redirects_to_intended_url_after_login(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $intendedUrl = '/subscription?package=1';

        $response = $this->withSession(['url.intended' => $intendedUrl])
            ->post('/login/custom', [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($intendedUrl);
    }

    public function test_user_ignores_admin_intended_url_and_redirects_to_dashboard(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // User tries to access admin route while logged out
        $adminIntendedUrl = '/admin/dashboard';

        $response = $this->withSession(['url.intended' => $adminIntendedUrl])
            ->post('/login/custom', [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        // Should redirect to user dashboard, NOT to admin route or access-denied
        $response->assertRedirect(route('user.dashboard'));
        $this->assertNotEquals($adminIntendedUrl, $response->headers->get('Location'));
    }

    public function test_user_ignores_admin_intended_url_and_redirects_to_subscription_when_no_subscription(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => false,
        ]);
        $user->assignRole('User');

        // User tries to access admin route while logged out
        $adminIntendedUrl = '/admin/users';

        $response = $this->withSession(['url.intended' => $adminIntendedUrl])
            ->post('/login/custom', [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        // Should redirect to subscription page, NOT to admin route or access-denied
        $response->assertRedirect(route('subscription'));
        $this->assertNotEquals($adminIntendedUrl, $response->headers->get('Location'));
    }

    public function test_user_redirects_to_safe_user_route_intended_url(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        $safeIntendedUrl = '/user/profile';

        $response = $this->withSession(['url.intended' => $safeIntendedUrl])
            ->post('/login/custom', [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($safeIntendedUrl);
    }

    public function test_super_admin_redirects_to_admin_intended_url(): void
    {
        $this->mockDeviceFingerprintService();

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Super Admin');

        $adminIntendedUrl = '/admin/users';

        $response = $this->withSession(['url.intended' => $adminIntendedUrl])
            ->post('/login/custom', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($admin);
        // Admin should be redirected to admin dashboard (not the intended URL directly, but admin routes are accessible)
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_does_not_get_access_denied_when_admin_intended_url_exists(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        // Simulate user trying to access admin route
        $adminIntendedUrl = url('/admin/dashboard');

        $response = $this->withSession(['url.intended' => $adminIntendedUrl])
            ->post('/login/custom', [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        // Should NOT redirect to access-denied
        $response->assertRedirect(route('user.dashboard'));
        $this->assertStringNotContainsString('access-denied', $response->headers->get('Location'));
    }

    public function test_user_redirects_to_subscription_page_when_no_active_subscription(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => false,
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('subscription'));
    }

    public function test_user_redirects_to_dashboard_when_has_active_subscription(): void
    {
        $this->mockDeviceFingerprintService();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
            'is_subscribed' => true,
        ]);
        $user->assignRole('User');

        $response = $this->post('/login/custom', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('user.dashboard'));
    }

    public function test_check_email_endpoint_returns_true_when_email_exists(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->post('/check-email', [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['exists' => true]);
    }

    public function test_check_email_endpoint_returns_false_when_email_does_not_exist(): void
    {
        $response = $this->post('/check-email', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['exists' => false]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $response = $this->actingAs($user)->post('/admin-logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_logout_and_redirects_to_admin_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->post('/admin-logout');

        $this->assertGuest();
        $response->assertRedirect(route('admin-login'));
    }

    public function test_sub_admin_can_logout_and_redirects_to_admin_login(): void
    {
        $subAdmin = User::factory()->create([
            'email' => 'subadmin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $subAdmin->assignRole('Sub Admin');

        $response = $this->actingAs($subAdmin)->post('/admin-logout');

        $this->assertGuest();
        $response->assertRedirect(route('admin-login'));
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('User');

        $sessionId = session()->getId();

        $this->actingAs($user)->post('/admin-logout');

        $this->assertGuest();
        $this->assertNotEquals($sessionId, session()->getId());
    }

    public function test_login_form_redirects_super_admin_to_admin_login_if_email_in_session(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('Super Admin');

        $response = $this->withSession(['email' => 'admin@example.com'])
            ->get('/login');

        $response->assertRedirect(route('admin-login'));
    }

    public function test_login_form_does_not_redirect_regular_user_to_admin_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);
        $user->assignRole('User');

        $response = $this->withSession(['email' => 'user@example.com'])
            ->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }
}

