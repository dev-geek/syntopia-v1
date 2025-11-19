<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FreePlanAttempt;
use App\Models\Package;
use App\Services\FreePlanAbuseService;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class FreePlanAbuseTest extends TestCase
{
    use RefreshDatabase;

    private FreePlanAbuseService $freePlanAbuseService;
    private DeviceFingerprintService $deviceFingerprintService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceFingerprintService = new DeviceFingerprintService();
        $this->freePlanAbuseService = new FreePlanAbuseService($this->deviceFingerprintService);
    }

    /** @test */
    public function user_can_use_free_plan_if_never_used_before()
    {
        $user = User::factory()->create();
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('Free plan is available for you.', $result['message']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_already_used()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('already_used', $result['reason']);
        $this->assertStringContainsString('already used the free plan', $result['message']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_currently_has_free_package()
    {
        $freePackage = Package::factory()->create(['name' => 'Free', 'price' => 0]);
        $user = User::factory()->create(['package_id' => $freePackage->id]);
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('already_used', $result['reason']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_has_free_plan_order_history()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->create(['name' => 'Free', 'price' => 0]);

        // Create a free plan order
        $user->orders()->create([
            'package' => 'Free',
            'amount' => 0,
            'status' => 'completed',
            'payment' => 'Free',
            'transaction_id' => 'test_tx_' . uniqid()
        ]);

        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('already_used', $result['reason']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_ip_is_blocked()
    {
        $user = User::factory()->create();
        $request = $this->createMockRequest(['ip' => '192.168.1.100']);

        // Block the IP
        FreePlanAttempt::create([
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test Agent',
            'device_fingerprint' => 'test_fingerprint',
            'email' => 'test@example.com',
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block'
        ]);

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('blocked', $result['reason']);
        $this->assertStringContainsString('blocked from using the free plan', $result['message']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_email_is_blocked()
    {
        $user = User::factory()->create();
        $request = $this->createMockRequest(['email' => 'blocked@example.com']);

        // Block the email
        FreePlanAttempt::create([
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test Agent',
            'device_fingerprint' => 'test_fingerprint',
            'email' => 'blocked@example.com',
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block'
        ]);

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('blocked', $result['reason']);
    }

    /** @test */
    public function user_cannot_use_free_plan_if_exceeded_max_attempts()
    {
        $user = User::factory()->create();
        $request = $this->createMockRequest(['ip' => '192.168.1.100']);

        // Create multiple attempts from same IP
        for ($i = 0; $i < 4; $i++) {
            FreePlanAttempt::create([
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Test Agent',
                'device_fingerprint' => 'test_fingerprint_' . $i,
                'email' => 'test' . $i . '@example.com',
                'is_blocked' => false
            ]);
        }

        $result = $this->freePlanAbuseService->canUseFreePlan($user, $request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('too_many_attempts', $result['reason']);
        $this->assertStringContainsString('Too many free plan attempts', $result['message']);
    }

    /** @test */
    public function free_plan_can_be_assigned_successfully()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->create(['name' => 'Free', 'price' => 0]);
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('assigned successfully', $result['message']);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);
        $this->assertTrue($user->has_used_free_plan);
        $this->assertNotNull($user->free_plan_used_at);
    }

    /** @test */
    public function free_plan_assignment_fails_if_user_already_used_free_plan()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used the free plan', $result['message']);
    }

    /** @test */
    public function device_information_is_recorded_on_login()
    {
        $user = User::factory()->create();
        $request = $this->createMockRequest();

        $this->deviceFingerprintService->recordUserDeviceInfo($user, $request);

        $user->refresh();
        $this->assertEquals($request->ip(), $user->last_ip);
        $this->assertNotNull($user->device_id);
        $this->assertNotNull($user->last_device_fingerprint);
        $this->assertNotNull($user->last_login_at);
    }

    /** @test */
    public function free_plan_attempt_is_recorded()
    {
        $request = $this->createMockRequest(['email' => 'test@example.com']);

        $this->deviceFingerprintService->recordAttempt($request);

        $this->assertDatabaseHas('free_plan_attempts', [
            'email' => 'test@example.com',
            'ip_address' => $request->ip()
        ]);
    }

    /** @test */
    public function user_cannot_downgrade_to_free_plan_if_already_used()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);

        $result = $this->freePlanAbuseService->canDowngradeToFree($user);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('already_used', $result['reason']);
        $this->assertStringContainsString('already used the free plan', $result['message']);
    }

    /** @test */
    public function user_can_downgrade_to_free_plan_if_never_used()
    {
        $user = User::factory()->create();

        $result = $this->freePlanAbuseService->canDowngradeToFree($user);

        $this->assertTrue($result['allowed']);
        $this->assertStringContainsString('can downgrade to the free plan', $result['message']);
    }

    /** @test */
    public function identifier_can_be_blocked()
    {
        $result = $this->freePlanAbuseService->blockIdentifier('ip', '192.168.1.100', 'Test block');

        $this->assertTrue($result);
        $this->assertDatabaseHas('free_plan_attempts', [
            'ip_address' => '192.168.1.100',
            'is_blocked' => true
        ]);
    }

    /** @test */
    public function identifier_can_be_unblocked()
    {
        // First block an identifier
        FreePlanAttempt::create([
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test Agent',
            'device_fingerprint' => 'test_fingerprint',
            'email' => 'test@example.com',
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block'
        ]);

        $result = $this->freePlanAbuseService->unblockIdentifier('ip', '192.168.1.100');

        $this->assertTrue($result);
        $this->assertDatabaseHas('free_plan_attempts', [
            'ip_address' => '192.168.1.100',
            'is_blocked' => false
        ]);
    }

    /** @test */
    public function abuse_statistics_are_calculated_correctly()
    {
        // Create some test data
        FreePlanAttempt::factory()->count(10)->create();
        FreePlanAttempt::factory()->count(3)->create(['is_blocked' => true]);

        $statistics = $this->freePlanAbuseService->getAbuseStatistics(30);

        $this->assertEquals(13, $statistics['total_attempts']);
        $this->assertEquals(3, $statistics['blocked_attempts']);
        $this->assertGreaterThan(0, $statistics['unique_ips']);
        $this->assertGreaterThan(0, $statistics['block_rate']);
    }

    private function createMockRequest(array $overrides = []): Request
    {
        $defaults = [
            'ip' => '192.168.1.100',
            'email' => 'test@example.com',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $data = array_merge($defaults, $overrides);

        $request = Request::create('/test', 'POST', $data);
        $request->headers->set('User-Agent', $data['user_agent']);
        $request->server->set('REMOTE_ADDR', $data['ip']);

        return $request;
    }
}
