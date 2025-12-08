<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Services\PasswordBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasswordBindingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordBindingService $passwordBindingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordBindingService = new PasswordBindingService();
    }

    /** @test */
    public function bind_password_succeeds_with_valid_password_and_tenant_id()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['status' => 'bound']
            ], 200)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error_message']);
        $this->assertNotNull($result['data']);
    }

    /** @test */
    public function bind_password_fails_when_password_format_is_invalid()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'short');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_fails_when_password_missing_uppercase()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'lowercase123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }

    /** @test */
    public function bind_password_fails_when_password_missing_lowercase()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'UPPERCASE123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }

    /** @test */
    public function bind_password_fails_when_password_missing_number()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'NoNumberHere!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }

    /** @test */
    public function bind_password_fails_when_password_missing_special_character()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'NoSpecial123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }

    /** @test */
    public function bind_password_fails_when_user_has_no_tenant_id()
    {
        $user = User::factory()->create([
            'tenant_id' => null
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot bind password without tenant_id', $result['error_message']);
        $this->assertFalse($result['swal']);
    }

    /** @test */
    public function bind_password_handles_idempotent_already_bound_response()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Password already bound',
                'data' => ['status' => 'already_bound']
            ], 200)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error_message']);
    }

    /** @test */
    public function bind_password_handles_already_bound_error_message()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 400,
                'message' => 'Password is already bound to this account'
            ], 200)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function bind_password_handles_http_400_error()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 400, 'message' => 'Bad Request'], 400)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        // When HTTP client throws exception, it's caught and returns system error
        // So we check for either the formatted error or system error
        $this->assertTrue(
            str_contains($result['error_message'], '[400]') ||
            str_contains($result['error_message'], '400') ||
            str_contains($result['error_message'], 'Bad Request')
        );
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_http_401_error()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 401, 'message' => 'Unauthorized'], 401)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[401]') ||
            str_contains($result['error_message'], '401') ||
            str_contains($result['error_message'], 'Unauthorized')
        );
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_http_404_error()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 404, 'message' => 'Not Found'], 404)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[404]') ||
            str_contains($result['error_message'], '404') ||
            str_contains($result['error_message'], 'Not Found')
        );
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_http_429_error()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 429, 'message' => 'Too Many Requests'], 429)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[429]') ||
            str_contains($result['error_message'], '429') ||
            str_contains($result['error_message'], 'Too Many Requests')
        );
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_http_500_error()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 500, 'message' => 'Internal Server Error'], 500)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[500]') ||
            str_contains($result['error_message'], '500') ||
            str_contains($result['error_message'], 'Internal Server Error')
        );
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_api_error_code_665()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 665,
                'message' => 'Application not activated'
            ], 200)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('application is not activated', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_api_error_code_730()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        // Code 730 with message not containing "already" or "bound" should be treated as error
        Http::fake([
            '*' => Http::response([
                'code' => 730,
                'message' => 'User registration error'
            ], 200)
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already registered', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_handles_exception()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        $result = $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('System error', $result['error_message']);
        $this->assertStringContainsString('Network error', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function bind_password_sends_correct_api_request()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => []
            ], 200)
        ]);

        $this->passwordBindingService->bindPassword($user, 'ValidPass123!');

        Http::assertSent(function ($request) use ($user) {
            $body = json_decode($request->body(), true);
            return $request->url() === config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp') . '/api/partner/tenant/user/password/bind' &&
                   $request->hasHeader('subscription-key') &&
                   $body['email'] === $user->email &&
                   $body['newPassword'] === 'ValidPass123!' &&
                   $body['phone'] === '';
        });
    }

    /** @test */
    public function bind_password_validates_password_length_minimum()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $result = $this->passwordBindingService->bindPassword($user, 'Abc1!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }

    /** @test */
    public function bind_password_validates_password_length_maximum()
    {
        $user = User::factory()->create([
            'tenant_id' => 'test-tenant-123'
        ]);

        $longPassword = 'A' . str_repeat('a1!', 20); // 61 characters
        $result = $this->passwordBindingService->bindPassword($user, $longPassword);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
    }
}

