<?php

namespace Tests\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\FreePlanAbuseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Mockery;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockServices(): void
    {
        $deviceFingerprintService = Mockery::mock(DeviceFingerprintService::class);
        $deviceFingerprintService->shouldReceive('isBlocked')->andReturn(false);
        $deviceFingerprintService->shouldReceive('hasRecentAttempts')->andReturn(false);
        $deviceFingerprintService->shouldReceive('recordAttempt')->andReturn(true);

        $freePlanAbuseService = Mockery::mock(FreePlanAbuseService::class);
        $freePlanAbuseService->shouldReceive('checkAbusePatterns')->andReturn([
            'allowed' => true,
            'reason' => null,
            'message' => null
        ]);

        $this->app->instance(DeviceFingerprintService::class, $deviceFingerprintService);
        $this->app->instance(FreePlanAbuseService::class, $freePlanAbuseService);
    }

    protected function mockMailService(bool $success = true): void
    {
        Mail::fake();
    }

    public function test_user_can_view_registration_form_with_email_parameter(): void
    {
        $response = $this->get('/register?email=test@example.com');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
        $response->assertSee('test@example.com');
    }

    public function test_registration_form_redirects_to_login_when_email_parameter_missing(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_registration_form_redirects_to_login_with_invalid_email_format(): void
    {
        $response = $this->get('/register?email=invalid-email');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_register_successfully(): void
    {
        $this->mockServices();
        $this->mockMailService(true);

        $email = 'newuser@example.com';
        $password = 'Test123!@#';

        $response = $this->post('/register', [
            'email' => $email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => $password,
            'fingerprint_id' => 'test-fingerprint-123'
        ], [
            'HTTP_REFERER' => '/register?email=' . urlencode($email)
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'John Doe',
            'status' => 0,
            'email_verified_at' => null
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('User'));
        $this->assertNotNull($user->verification_code);
        $this->assertEquals(6, strlen($user->verification_code));

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_fails_when_email_mismatch_with_url_parameter(): void
    {
        $this->mockServices();

        $response = $this->post('/register?email=original@example.com', [
            'email' => 'different@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
            'fingerprint_id' => 'test-fingerprint-123'
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'different@example.com']);
    }

    public function test_registration_fails_when_email_is_required(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_fails_when_email_is_invalid_format(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'invalid-email',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_fails_when_email_already_exists(): void
    {
        $this->mockServices();

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ], [
            'HTTP_REFERER' => '/register?email=existing@example.com'
        ]);

        $response->assertSessionHasErrors('email');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('already registered', $errors->first('email'));
    }

    public function test_registration_fails_when_first_name_is_required(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_registration_fails_when_last_name_is_required(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('last_name');
    }

    public function test_registration_fails_when_password_is_required(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_fails_when_password_is_too_short(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test1!',
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('at least 8 characters', $errors->first('password'));
    }

    public function test_registration_fails_when_password_is_too_long(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#' . str_repeat('a', 30),
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('not exceed 30 characters', $errors->first('password'));
    }

    public function test_registration_fails_when_password_missing_uppercase(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'test123!@#',
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('uppercase letter', $errors->first('password'));
    }

    public function test_registration_fails_when_password_missing_lowercase(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'TEST123!@#',
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('lowercase letter', $errors->first('password'));
    }

    public function test_registration_fails_when_password_missing_number(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'TestPass!@#',
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('number', $errors->first('password'));
    }

    public function test_registration_fails_when_password_missing_special_character(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test1234',
        ]);

        $response->assertSessionHasErrors('password');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('special character', $errors->first('password'));
    }

    public function test_registration_fails_when_first_name_exceeds_max_length(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => str_repeat('a', 256),
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('first_name');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('not exceed 255 characters', $errors->first('first_name'));
    }

    public function test_registration_fails_when_last_name_exceeds_max_length(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => str_repeat('a', 256),
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('last_name');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('not exceed 255 characters', $errors->first('last_name'));
    }

    public function test_registration_fails_when_email_exceeds_max_length(): void
    {
        $this->mockServices();

        $response = $this->post('/register', [
            'email' => str_repeat('a', 250) . '@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('not exceed 255 characters', $errors->first('email'));
    }

    public function test_registration_blocks_when_free_plan_abuse_detected(): void
    {
        $deviceFingerprintService = Mockery::mock(DeviceFingerprintService::class);
        $deviceFingerprintService->shouldReceive('isBlocked')->andReturn(false);
        $deviceFingerprintService->shouldReceive('hasRecentAttempts')->andReturn(false);

        $freePlanAbuseService = Mockery::mock(FreePlanAbuseService::class);
        $freePlanAbuseService->shouldReceive('checkAbusePatterns')->andReturn([
            'allowed' => false,
            'reason' => 'suspicious_pattern',
            'message' => 'Registration blocked due to abuse patterns detected.'
        ]);

        $this->app->instance(DeviceFingerprintService::class, $deviceFingerprintService);
        $this->app->instance(FreePlanAbuseService::class, $freePlanAbuseService);

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ], [
            'HTTP_REFERER' => '/register?email=test@example.com'
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $errors = $response->baseResponse->getSession()->get('errors');
        $this->assertStringContainsString('abuse patterns', $errors->first('email'));
    }

    public function test_registration_blocks_when_device_fingerprint_is_blocked(): void
    {
        $deviceFingerprintService = Mockery::mock(DeviceFingerprintService::class);
        $deviceFingerprintService->shouldReceive('isBlocked')->andReturn(true);
        $deviceFingerprintService->shouldReceive('hasRecentAttempts')->andReturn(false);

        $freePlanAbuseService = Mockery::mock(FreePlanAbuseService::class);

        $this->app->instance(DeviceFingerprintService::class, $deviceFingerprintService);
        $this->app->instance(FreePlanAbuseService::class, $freePlanAbuseService);

        $response = $this->post('/register?email=test@example.com', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Test123!@#',
        ]);

        $response->assertStatus(403);
        $response->assertSee('Registration is not allowed from this device');
    }

    public function test_registration_creates_user_even_if_mail_fails(): void
    {
        $this->mockServices();
        Mail::fake();

        $email = 'newuser@example.com';
        $password = 'Test123!@#';

        $response = $this->post('/register', [
            'email' => $email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => $password,
            'fingerprint_id' => 'test-fingerprint-123'
        ], [
            'HTTP_REFERER' => '/register?email=' . urlencode($email)
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->verification_code);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_preserves_intended_url_in_session(): void
    {
        $this->mockServices();
        $this->mockMailService(true);

        $email = 'newuser@example.com';
        $intendedUrl = '/subscription?package=1';

        $response = $this->withSession(['url.intended' => $intendedUrl])
            ->post('/register', [
                'email' => $email,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => 'Test123!@#',
                'fingerprint_id' => 'test-fingerprint-123'
            ], [
                'HTTP_REFERER' => '/register?email=' . urlencode($email)
            ]);

        $this->assertTrue(session()->has('verification_intended_url'));
        $this->assertEquals($intendedUrl, session('verification_intended_url'));
    }

    public function test_registration_creates_user_with_correct_subscriber_password(): void
    {
        $this->mockServices();
        $this->mockMailService(true);

        $email = 'newuser@example.com';
        $password = 'Test123!@#';

        $response = $this->post('/register', [
            'email' => $email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => $password,
            'fingerprint_id' => 'test-fingerprint-123'
        ], [
            'HTTP_REFERER' => '/register?email=' . urlencode($email)
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals($password, $user->subscriber_password);
    }
}

