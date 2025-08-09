<?php

namespace Tests\Feature;

use App\Models\FreePlanAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FreePlanAbuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prevents_multiple_registrations_from_same_device()
    {
        // Simulate a device fingerprint
        $fingerprintId = 'test_fp_' . Str::random(32);
        
        // First registration attempt should succeed
        $response1 = $this->registerWithFingerprint($fingerprintId);
        $response1->assertRedirect('/email/verify');
        
        // Second registration attempt with same fingerprint should be blocked
        $response2 = $this->registerWithFingerprint($fingerprintId);
        $response2->assertStatus(403);
    }

    public function test_it_allows_registrations_from_different_devices()
    {
        // First registration with fingerprint 1
        $response1 = $this->registerWithFingerprint('fp1_' . Str::random(30));
        $response1->assertRedirect('/email/verify');
        
        // Second registration with different fingerprint should succeed
        $response2 = $this->registerWithFingerprint('fp2_' . Str::random(30));
        $response2->assertRedirect('/email/verify');
    }

    public function test_it_blocks_registration_after_max_attempts()
    {
        $email = 'test@example.com';
        
        // Create max_attempts + 1 registration attempts
        for ($i = 0; $i < config('free_plan_abuse.max_attempts', 3) + 1; $i++) {
            $response = $this->registerWithFingerprint("fp_$i", "user$i@example.com");
            
            if ($i < config('free_plan_abuse.max_attempts', 3)) {
                $response->assertRedirect('/email/verify');
            } else {
                $response->assertStatus(403);
            }
        }
    }

    protected function registerWithFingerprint(string $fingerprintId, string $email = null)
    {
        $email = $email ?? 'test_' . Str::random(10) . '@example.com';
        
        // First visit the registration page with email
        $this->get(route('register', ['email' => $email]));
        
        // Submit the registration form with fingerprint
        return $this->post(route('register'), [
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'fingerprint_id' => $fingerprintId,
            'timezone' => 'UTC',
            'screen_resolution' => '1920x1080',
            'color_depth' => '24',
        ]);
    }
}
