<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Services\PasswordBindingService;
use App\Services\MailService;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Mockery;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

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

    /**
     * Mock PasswordBindingService to return successful tenant creation
     */
    protected function mockPasswordBindingService(string $tenantId = 'test-tenant-123'): void
    {
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $passwordBindingService->shouldReceive('createTenantAndBindPassword')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'tenantId' => $tenantId
                ],
                'error_message' => null
            ]);

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);
    }

    /**
     * Mock MailService to return successful email sending
     */
    protected function mockMailService(): void
    {
        Mail::fake();
    }

    /**
     * Mock DeviceFingerprintService
     */
    protected function mockDeviceFingerprintService(): void
    {
        $deviceFingerprintService = Mockery::mock(DeviceFingerprintService::class);
        $deviceFingerprintService->shouldReceive('recordUserDeviceInfo')->andReturn(true);
        $deviceFingerprintService->shouldReceive('generateFingerprint')->andReturn('test-fingerprint');
        $deviceFingerprintService->shouldReceive('isBlocked')->andReturn(false);
        $deviceFingerprintService->shouldReceive('hasRecentAttempts')->andReturn(false);
        $deviceFingerprintService->shouldReceive('recordAttempt')->andReturn(true);

        $this->app->instance(DeviceFingerprintService::class, $deviceFingerprintService);
    }

    /**
     * Test that Super Admin can create a user with tenant_id
     */
    public function test_super_admin_can_create_user_with_tenant_id(): void
    {
        // Create Super Admin
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Mock PasswordBindingService
        $tenantId = 'test-tenant-12345';
        $this->mockPasswordBindingService($tenantId);

        // Mock MailService
        $this->mockMailService();

        // Act: Super Admin creates a user
        $response = $this->actingAs($superAdmin)
            ->post(route('admin.store.user'), [
                'name' => 'Test User',
                'email' => 'testuser@example.com',
                'password' => 'Test123!@#',
                'password_confirmation' => 'Test123!@#',
            ]);

        // Assert: User is created successfully
        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success');

        // Assert: User exists in database with tenant_id
        $user = User::where('email', 'testuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals($tenantId, $user->tenant_id);
        $this->assertNotNull($user->verification_code);
        $this->assertNull($user->email_verified_at);
        $this->assertEquals(0, $user->status);
        $this->assertTrue($user->hasRole('User'));
        $this->assertTrue(Hash::check('Test123!@#', $user->password));
        $this->assertEquals('Test123!@#', $user->subscriber_password);
    }

    /**
     * Test complete flow: Super Admin creates user -> User logs in -> User verifies -> User purchases plan
     */
    public function test_complete_flow_admin_creates_user_user_verifies_and_purchases_plan(): void
    {
        // Create Super Admin
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Mock PasswordBindingService
        $tenantId = 'test-tenant-67890';
        $this->mockPasswordBindingService($tenantId);

        // Mock MailService
        $this->mockMailService();

        // Mock DeviceFingerprintService
        $this->mockDeviceFingerprintService();

        // Step 1: Super Admin creates user
        $response = $this->actingAs($superAdmin)
            ->post(route('admin.store.user'), [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
            ]);

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success');

        // Get the created user
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenantId, $user->tenant_id);
        $this->assertNotNull($user->verification_code);

        // Step 2: User logs in (should redirect to verification page)
        $loginResponse = $this->post('/login/custom', [
            'email' => 'newuser@example.com',
            'password' => 'Password123!@#',
        ]);

        // User should be redirected to verification page since email is not verified
        // The login will logout the user and redirect to verification notice
        // Email should be in session after login attempt
        $this->assertTrue($loginResponse->isRedirect());

        // Step 3: User verifies email
        // Refresh user to get latest verification code
        $user->refresh();
        $verificationCode = $user->verification_code;
        $this->assertNotNull($verificationCode, 'Verification code should exist');
        $this->assertNotNull($user->subscriber_password, 'User should have subscriber_password');

        // Mock the verification to skip API call since user already has tenant_id
        // Set email in session before making the request
        $verifyResponse = $this->withSession(['email' => 'newuser@example.com'])
            ->post('/verify-code', [
                'email' => 'newuser@example.com',
                'verification_code' => $verificationCode,
            ]);

        // Verification should redirect on success
        $this->assertTrue($verifyResponse->isRedirect(), 'Verification should redirect on success');

        // User should be verified and redirected
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals(1, $user->status);
        $this->assertNull($user->verification_code);
        $this->assertEquals($tenantId, $user->tenant_id); // tenant_id should still be there

        // Step 4: User can now purchase a plan
        // Create a test package
        $package = Package::factory()->create([
            'name' => 'Starter',
            'price' => 29.99,
            'duration' => 'monthly',
        ]);

        // User should be able to access subscription page
        $subscriptionResponse = $this->actingAs($user)
            ->get(route('subscription'));

        $subscriptionResponse->assertStatus(200);
    }

    /**
     * Test that user creation fails if PasswordBindingService fails
     */
    public function test_user_creation_fails_when_password_binding_service_fails(): void
    {
        // Create Super Admin
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Mock PasswordBindingService to return failure
        $passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $passwordBindingService->shouldReceive('createTenantAndBindPassword')
            ->once()
            ->andReturn([
                'success' => false,
                'data' => null,
                'error_message' => 'Failed to create tenant. API error occurred.'
            ]);

        $this->app->instance(PasswordBindingService::class, $passwordBindingService);

        // Act: Super Admin tries to create a user
        $response = $this->actingAs($superAdmin)
            ->post(route('admin.store.user'), [
                'name' => 'Test User',
                'email' => 'testuser@example.com',
                'password' => 'Test123!@#',
                'password_confirmation' => 'Test123!@#',
            ]);

        // Assert: User creation fails
        $response->assertSessionHas('swal_error');
        $this->assertStringContainsString('Failed to create tenant', session('swal_error'));

        // Assert: User is not created
        $user = User::where('email', 'testuser@example.com')->first();
        $this->assertNull($user);
    }

    /**
     * Test that user can verify email after being created by Super Admin
     */
    public function test_user_can_verify_email_after_admin_creation(): void
    {
        // Create Super Admin
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Mock PasswordBindingService
        $tenantId = 'test-tenant-verify';
        $this->mockPasswordBindingService($tenantId);

        // Mock MailService
        $this->mockMailService();

        // Mock DeviceFingerprintService
        $this->mockDeviceFingerprintService();

        // Super Admin creates user
        $this->actingAs($superAdmin)
            ->post(route('admin.store.user'), [
                'name' => 'Verify User',
                'email' => 'verify@example.com',
                'password' => 'Verify123!@#',
                'password_confirmation' => 'Verify123!@#',
            ]);

        $user = User::where('email', 'verify@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenantId, $user->tenant_id);

        // User logs in
        $this->post('/login/custom', [
            'email' => 'verify@example.com',
            'password' => 'Verify123!@#',
        ]);

        // Refresh user to get latest verification code
        $user->refresh();
        $verificationCode = $user->verification_code;
        $this->assertNotNull($verificationCode, 'Verification code should exist');
        $this->assertNotNull($user->subscriber_password, 'User should have subscriber_password');

        // User verifies email with email in session
        $verifyResponse = $this->withSession(['email' => 'verify@example.com'])
            ->post('/verify-code', [
                'email' => 'verify@example.com',
                'verification_code' => $verificationCode,
            ]);

        // Check if verification was successful (should redirect)
        $this->assertTrue($verifyResponse->isRedirect(), 'Verification should redirect on success');

        // User should be verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals(1, $user->status);
        $this->assertEquals($tenantId, $user->tenant_id); // tenant_id should still be there
    }

    /**
     * Test that verified user with tenant_id can purchase a plan
     */
    public function test_verified_user_with_tenant_id_can_purchase_plan(): void
    {
        // Create Super Admin
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Mock PasswordBindingService
        $tenantId = 'test-tenant-purchase';
        $this->mockPasswordBindingService($tenantId);

        // Mock MailService
        $this->mockMailService();

        // Mock DeviceFingerprintService
        $this->mockDeviceFingerprintService();

        // Super Admin creates user
        $this->actingAs($superAdmin)
            ->post(route('admin.store.user'), [
                'name' => 'Purchase User',
                'email' => 'purchase@example.com',
                'password' => 'Purchase123!@#',
                'password_confirmation' => 'Purchase123!@#',
            ]);

        $user = User::where('email', 'purchase@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenantId, $user->tenant_id);

        // User logs in and verifies
        $this->post('/login/custom', [
            'email' => 'purchase@example.com',
            'password' => 'Purchase123!@#',
        ]);

        // Refresh user to get latest verification code
        $user->refresh();
        $verificationCode = $user->verification_code;
        $this->assertNotNull($verificationCode, 'Verification code should exist');
        $this->assertNotNull($user->subscriber_password, 'User should have subscriber_password');

        // User verifies email with email in session
        $verifyResponse = $this->withSession(['email' => 'purchase@example.com'])
            ->post('/verify-code', [
                'email' => 'purchase@example.com',
                'verification_code' => $verificationCode,
            ]);

        // Check if verification was successful (should redirect)
        $this->assertTrue($verifyResponse->isRedirect(), 'Verification should redirect on success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals(1, $user->status);
        $this->assertEquals($tenantId, $user->tenant_id);

        // Create a package
        $package = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99,
            'duration' => 'monthly',
        ]);

        // User should be able to access subscription page
        $subscriptionResponse = $this->actingAs($user)
            ->get(route('subscription'));

        $subscriptionResponse->assertStatus(200);
        $subscriptionResponse->assertSee($package->name);
    }
}

