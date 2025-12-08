<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Services\TenantAssignmentService;
use App\Services\PasswordBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

class TenantAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantAssignmentService $tenantAssignmentService;
    private PasswordBindingService $passwordBindingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordBindingService = Mockery::mock(PasswordBindingService::class);
        $this->tenantAssignmentService = new TenantAssignmentService($this->passwordBindingService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function assign_tenant_returns_success_when_user_already_has_tenant_id()
    {
        $user = User::factory()->create([
            'tenant_id' => 'existing-tenant-123'
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('existing-tenant-123', $result['tenant_id']);
        $this->assertEquals('User already has tenant_id', $result['message']);
    }

    /** @test */
    public function assign_tenant_fails_when_password_is_missing()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => null
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot assign tenant_id without password', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_uses_provided_password_over_subscriber_password()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'OldPass123!'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['tenantId' => 'new-tenant-456']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->with($user, 'NewPass123!')
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user, 'NewPass123!');

        $this->assertTrue($result['success']);
        $this->assertEquals('new-tenant-456', $result['tenant_id']);
    }

    /** @test */
    public function assign_tenant_uses_subscriber_password_when_no_password_provided()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'SubPass123!'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['tenantId' => 'new-tenant-456']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->with($user, 'SubPass123!')
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('new-tenant-456', $result['tenant_id']);
    }

    /** @test */
    public function assign_tenant_fails_when_password_format_is_invalid()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'invalid'
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_succeeds_when_tenant_creation_is_successful()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['tenantId' => 'new-tenant-789']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('new-tenant-789', $result['tenant_id']);
        $this->assertEquals('Tenant_id assigned successfully', $result['message']);

        $user->refresh();
        $this->assertEquals('new-tenant-789', $user->tenant_id);
    }

    /** @test */
    public function assign_tenant_handles_existing_tenant_with_tenant_id_in_response()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 730,
                'message' => 'User is already registered in the system',
                'data' => ['tenantId' => 'existing-tenant-999']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('existing-tenant-999', $result['tenant_id']);

        $user->refresh();
        $this->assertEquals('existing-tenant-999', $user->tenant_id);
    }

    /** @test */
    public function assign_tenant_uses_tenant_id_from_response_when_api_returns_730()
    {
        // Test that when API returns code 730 (user already registered)
        // and provides tenantId in response, the service uses it
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 730,
                'message' => 'User is already registered in the system',
                'data' => ['tenantId' => 'existing-tenant-from-api']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('existing-tenant-from-api', $result['tenant_id']);

        $user->refresh();
        $this->assertEquals('existing-tenant-from-api', $user->tenant_id);
    }

    /** @test */
    public function assign_tenant_uses_existing_user_tenant_id_when_duplicate_email_found()
    {
        // Create existing user with tenant_id
        $existingUser = User::factory()->create([
            'tenant_id' => 'existing-tenant-111',
            'email' => 'duplicate@example.com'
        ]);

        // Create a new user - we can't create duplicate emails due to DB constraint
        // So we'll test the scenario where API returns 730 with tenantId in response
        // The service should use that tenantId (which matches the existing user's tenant_id)
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 730,
                'message' => 'User is already registered in the system',
                'data' => ['tenantId' => 'existing-tenant-111']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertTrue($result['success']);
        $this->assertEquals('existing-tenant-111', $result['tenant_id']);

        $user->refresh();
        $this->assertEquals('existing-tenant-111', $user->tenant_id);
    }

    /** @test */
    public function assign_tenant_fails_when_existing_tenant_has_no_tenant_id()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'email' => 'test@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 730,
                'message' => 'User is already registered in the system',
                'data' => []
            ], 200)
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already registered', $result['error_message']);
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_handles_http_400_error()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 400, 'message' => 'Bad Request'], 400)
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[400]') ||
            str_contains($result['error_message'], '400') ||
            str_contains($result['error_message'], 'Bad Request')
        );
        // swal should be true (service now returns swal in all error cases)
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_handles_http_401_error()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 401, 'message' => 'Unauthorized'], 401)
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[401]') ||
            str_contains($result['error_message'], '401') ||
            str_contains($result['error_message'], 'Unauthorized')
        );
        // swal should be true (service now returns swal in all error cases)
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_handles_http_500_error()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake([
            '*' => Http::response(['code' => 500, 'message' => 'Internal Server Error'], 500)
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertTrue(
            str_contains($result['error_message'], '[500]') ||
            str_contains($result['error_message'], '500') ||
            str_contains($result['error_message'], 'Internal Server Error')
        );
        // swal should be true (service now returns swal in all error cases)
        $this->assertTrue($result['swal']);
    }

    /** @test */
    public function assign_tenant_fails_when_tenant_id_missing_from_response()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => []
            ], 200)
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to assign tenant_id', $result['message']);
    }

    /** @test */
    public function assign_tenant_continues_even_when_password_binding_fails()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['tenantId' => 'new-tenant-456']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn([
                'success' => false,
                'data' => null,
                'error_message' => 'Password binding failed'
            ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        // Tenant assignment should still succeed even if password binding fails
        $this->assertTrue($result['success']);
        $this->assertEquals('new-tenant-456', $result['tenant_id']);

        $user->refresh();
        $this->assertEquals('new-tenant-456', $user->tenant_id);
    }

    /** @test */
    public function assign_tenant_handles_exception()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!'
        ]);

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('System error', $result['error_message']);
        $this->assertStringContainsString('Network error', $result['error_message']);
        // swal should be present when exception is caught in assignTenant
        $this->assertTrue($result['swal'] ?? false);
    }

    /** @test */
    public function assign_tenant_sends_correct_api_request()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'ValidPass123!',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['tenantId' => 'new-tenant-123']
            ], 200)
        ]);

        $this->passwordBindingService
            ->shouldReceive('bindPassword')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'error_message' => null]);

        $this->tenantAssignmentService->assignTenant($user);

        Http::assertSent(function ($request) use ($user) {
            $body = json_decode($request->body(), true);
            return $request->url() === config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp') . '/api/partner/tenant/create' &&
                   $request->hasHeader('subscription-key') &&
                   $body['name'] === $user->name &&
                   $body['adminEmail'] === $user->email &&
                   $body['adminPassword'] === 'ValidPass123!' &&
                   $body['regionCode'] === 'OTHER' &&
                   $body['appIds'] === [1];
        });
    }

    /** @test */
    public function assign_tenant_validates_password_format()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'subscriber_password' => 'short'
        ]);

        $result = $this->tenantAssignmentService->assignTenant($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password format is invalid', $result['error_message']);
        $this->assertTrue($result['swal']);
    }
}

