<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FreePlanAttempt;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class FreePlanAbuseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable bypass in testing environment so we can test abuse patterns
        config(['free_plan_abuse.bypass_in_testing' => false]);
    }

    /** @test */
    public function authenticated_user_can_check_free_plan_eligibility()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/free-plan/eligibility');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'allowed',
                        'message'
                    ]
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_check_eligibility()
    {
        $response = $this->getJson('/api/free-plan/eligibility');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_assign_free_plan_when_eligible()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->create(['name' => 'Free', 'price' => 0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/free-plan/assign');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'package',
                        'user'
                    ]
                ]);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);
    }

    /** @test */
    public function user_cannot_assign_free_plan_when_already_used()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/free-plan/assign');

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'error_code'
                ]);
    }

    /** @test */
    public function user_can_get_free_plan_status()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/free-plan/status');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'has_used_free_plan',
                        'can_access_free_plan',
                        'is_device_blocked',
                        'current_package',
                        'free_plan_used_at',
                        'last_ip',
                        'last_login_at'
                    ]
                ]);
    }

    /** @test */
    public function user_can_report_suspicious_activity()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/free-plan/report-suspicious', [
            'description' => 'Suspicious activity detected',
            'evidence' => 'Multiple failed attempts from same IP'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function report_suspicious_activity_requires_description()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/free-plan/report-suspicious', [
            'evidence' => 'Some evidence'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['description']);
    }

    /** @test */
    public function free_plan_eligibility_returns_correct_status_for_new_user()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/free-plan/eligibility');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'allowed' => true
                    ]
                ]);
    }

    /** @test */
    public function free_plan_eligibility_returns_correct_status_for_user_who_used_free_plan()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/free-plan/eligibility');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'allowed' => false,
                        'reason' => 'already_used'
                    ]
                ]);
    }

    /** @test */
    public function free_plan_eligibility_returns_blocked_status_when_ip_is_blocked()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Block the user's IP
        FreePlanAttempt::create([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'device_fingerprint' => 'test_fingerprint',
            'email' => 'test@example.com',
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block'
        ]);

        $response = $this->getJson('/api/free-plan/eligibility');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'allowed' => false,
                        'reason' => 'blocked'
                    ]
                ]);
    }

    /** @test */
    public function free_plan_status_includes_device_information()
    {
        $user = User::factory()->create([
            'last_ip' => '192.168.1.100',
            'device_id' => 'test_device_id',
            'last_device_fingerprint' => 'test_fingerprint',
            'last_login_at' => now()
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/free-plan/status');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'last_ip' => '192.168.1.100',
                        'last_login_at' => $user->last_login_at->toISOString()
                    ]
                ]);
    }
}
