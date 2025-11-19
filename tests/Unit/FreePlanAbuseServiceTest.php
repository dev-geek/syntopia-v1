<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\FreePlanAttempt;
use App\Models\Package;
use App\Services\FreePlanAbuseService;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;

class FreePlanAbuseServiceTest extends TestCase
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
    public function has_used_free_plan_returns_true_when_user_has_used_free_plan_flag()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);

        $result = $this->freePlanAbuseService->hasUsedFreePlan($user);

        $this->assertTrue($result);
    }

    /** @test */
    public function has_used_free_plan_returns_true_when_user_has_free_package()
    {
        $freePackage = Package::factory()->free()->create();
        $user = User::factory()->create(['package_id' => $freePackage->id]);

        $result = $this->freePlanAbuseService->hasUsedFreePlan($user);

        $this->assertTrue($result);
    }

    /** @test */
    public function has_used_free_plan_returns_true_when_user_has_free_plan_order()
    {
        $user = User::factory()->create();
        $user->orders()->create([
            'package' => 'Free',
            'amount' => 0,
            'status' => 'completed',
            'payment' => 'Free',
            'transaction_id' => 'test_transaction_' . uniqid()
        ]);

        $result = $this->freePlanAbuseService->hasUsedFreePlan($user);

        $this->assertTrue($result);
    }

    /** @test */
    public function has_used_free_plan_returns_false_when_user_never_used_free_plan()
    {
        $user = User::factory()->create();

        $result = $this->freePlanAbuseService->hasUsedFreePlan($user);

        $this->assertFalse($result);
    }

    /** @test */
    public function check_abuse_patterns_returns_allowed_when_no_abuse_detected()
    {
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->checkAbusePatterns($request);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('No abuse patterns detected.', $result['message']);
    }

    /** @test */
    public function check_abuse_patterns_returns_blocked_when_ip_is_blocked()
    {
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

        $result = $this->freePlanAbuseService->checkAbusePatterns($request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('blocked', $result['reason']);
    }

    /** @test */
    public function check_abuse_patterns_returns_too_many_attempts_when_limit_exceeded()
    {
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

        $result = $this->freePlanAbuseService->checkAbusePatterns($request);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('too_many_attempts', $result['reason']);
    }

    /** @test */
    public function assign_free_plan_successfully_assigns_package()
    {
        $user = User::factory()->create();
        $freePackage = Package::factory()->free()->create();
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('assigned successfully', $result['message']);

        $user->refresh();
        $this->assertEquals($freePackage->id, $user->package_id);
        $this->assertTrue($user->is_subscribed);
    }

    /** @test */
    public function assign_free_plan_fails_when_user_already_used_free_plan()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);
        $request = $this->createMockRequest();

        $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used the free plan', $result['message']);
    }

    /** @test */
    public function assign_free_plan_fails_when_abuse_patterns_detected()
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

        $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('blocked from using the free plan', $result['message']);
    }

    /** @test */
    public function can_downgrade_to_free_returns_false_when_user_already_used_free_plan()
    {
        $user = User::factory()->create(['has_used_free_plan' => true]);

        $result = $this->freePlanAbuseService->canDowngradeToFree($user);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('already_used', $result['reason']);
    }

    /** @test */
    public function can_downgrade_to_free_returns_true_when_user_never_used_free_plan()
    {
        $user = User::factory()->create();

        $result = $this->freePlanAbuseService->canDowngradeToFree($user);

        $this->assertTrue($result['allowed']);
        $this->assertStringContainsString('can downgrade to the free plan', $result['message']);
    }

    /** @test */
    public function block_identifier_blocks_all_matching_attempts()
    {
        // Create some attempts
        FreePlanAttempt::factory()->count(3)->create(['ip_address' => '192.168.1.100']);
        FreePlanAttempt::factory()->count(2)->create(['ip_address' => '192.168.1.101']);

        $result = $this->freePlanAbuseService->blockIdentifier('ip', '192.168.1.100', 'Test block');

        $this->assertTrue($result);
        $this->assertEquals(3, FreePlanAttempt::where('ip_address', '192.168.1.100')->where('is_blocked', true)->count());
        $this->assertEquals(2, FreePlanAttempt::where('ip_address', '192.168.1.101')->where('is_blocked', false)->count());
    }

    /** @test */
    public function unblock_identifier_unblocks_all_matching_attempts()
    {
        // Create blocked attempts
        FreePlanAttempt::factory()->count(3)->create([
            'ip_address' => '192.168.1.100',
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block'
        ]);

        $result = $this->freePlanAbuseService->unblockIdentifier('ip', '192.168.1.100');

        $this->assertTrue($result);
        $this->assertEquals(3, FreePlanAttempt::where('ip_address', '192.168.1.100')->where('is_blocked', false)->count());
    }

    /** @test */
    public function get_abuse_statistics_returns_correct_data()
    {
        // Create test data
        FreePlanAttempt::factory()->count(10)->create();
        FreePlanAttempt::factory()->count(3)->create(['is_blocked' => true]);

        $statistics = $this->freePlanAbuseService->getAbuseStatistics(30);

        $this->assertArrayHasKey('total_attempts', $statistics);
        $this->assertArrayHasKey('blocked_attempts', $statistics);
        $this->assertArrayHasKey('unique_ips', $statistics);
        $this->assertArrayHasKey('unique_emails', $statistics);
        $this->assertArrayHasKey('unique_devices', $statistics);
        $this->assertArrayHasKey('block_rate', $statistics);
        $this->assertEquals(13, $statistics['total_attempts']);
        $this->assertEquals(3, $statistics['blocked_attempts']);
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

        // Set up session for the request
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
