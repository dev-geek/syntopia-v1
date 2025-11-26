<?php

namespace App\Services;

use App\Models\User;
use App\Models\FreePlanAttempt;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FreePlanAbuseService
{
    private DeviceFingerprintService $deviceFingerprintService;

    public function __construct(DeviceFingerprintService $deviceFingerprintService)
    {
        $this->deviceFingerprintService = $deviceFingerprintService;
    }

    private function isPrivilegedUser(Request $request, ?User $user = null): bool
    {
        try {
            $actor = $user ?: $request->user();
            if (!$actor) {
                return false;
            }
            if (method_exists($actor, 'hasAnyRole')) {
                return $actor->hasAnyRole(['Super Admin', 'Sub Admin']);
            }
            if (method_exists($actor, 'hasRole')) {
                return $actor->hasRole('Super Admin') || $actor->hasRole('Sub Admin');
            }
        } catch (\Throwable $e) {
            // ignore and treat as not privileged
        }
        return false;
    }

    /**
     * Check if a user can use the free plan
     */
    public function canUseFreePlan(User $user, Request $request): array
    {
        // If abuse prevention is disabled, allow all users
        if (!config('free_plan_abuse.enabled', false)) {
            return [
                'allowed' => true,
                'message' => 'Free plan is available for you.'
            ];
        }

        try {
            if ($this->isPrivilegedUser($request, $user)) {
                return [
                    'allowed' => true,
                    'message' => 'Free plan is available for admin roles.'
                ];
            }
            // Check if user has already used free plan
            if ($this->hasUsedFreePlan($user)) {
                return [
                    'allowed' => false,
                    'reason' => 'already_used',
                    'message' => 'You have exceeded your limit to use the Free plan. Please buy a plan.',
                    'error_code' => 'FREE_PLAN_ALREADY_USED'
                ];
            }

            // Check for abuse patterns
            $abuseCheck = $this->checkAbusePatterns($request);
            if (!$abuseCheck['allowed']) {
                return $abuseCheck;
            }

            return [
                'allowed' => true,
                'message' => 'Free plan is available for you.'
            ];

        } catch (\Exception $e) {
            Log::error('Error checking free plan eligibility', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'allowed' => false,
                'reason' => 'system_error',
                'message' => 'Unable to verify free plan eligibility. Please contact support.',
                'error_code' => 'SYSTEM_ERROR'
            ];
        }
    }

    /**
     * Check if user has already used the free plan
     */
    public function hasUsedFreePlan(User $user): bool
    {
        // If abuse prevention is disabled, always return false (user hasn't used free plan)
        if (!config('free_plan_abuse.enabled', false)) {
            return false;
        }

        // Check if user has the has_used_free_plan flag set
        if ($user->has_used_free_plan) {
            return true;
        }

        // Check if user has ever had a free package assigned via relation
        $hasFreePackageHistory = $user->orders()
            ->whereHas('package', function ($query) {
                $query->where('name', 'Free');
            })
            ->exists();

        // Check if user currently has free package
        $hasCurrentFreePackage = $user->package &&
            strtolower($user->package->name) === 'free';

        // Also consider completed zero-amount orders as free usage
        $hasZeroAmountCompletedOrder = $user->orders()
            ->where('status', 'completed')
            ->where('amount', 0)
            ->exists();

        // Only package history/current, explicit flag, or zero-amount completed order qualifies as used
        return $hasFreePackageHistory || $hasCurrentFreePackage || $hasZeroAmountCompletedOrder;
    }

    /**
     * Check for abuse patterns (IP, device, email)
     */
    public function checkAbusePatterns(Request $request): array
    {
        // If abuse prevention is disabled, allow all requests
        if (!config('free_plan_abuse.enabled', false)) {
            return [
                'allowed' => true,
                'message' => 'No abuse patterns detected.'
            ];
        }

        try {
            if ($this->isPrivilegedUser($request)) {
                return [
                    'allowed' => true,
                    'message' => 'Admin roles are exempt from abuse checks.'
                ];
            }
            $ip = $request->ip();
            $email = $request->input('email');
            $deviceFingerprint = $this->deviceFingerprintService->generateFingerprint($request);
            $fingerprintId = $request->cookie('fp_id', '');

            // Check if any identifier is blocked
            if ($this->isBlocked($ip, $email, $deviceFingerprint, $fingerprintId)) {
                return [
                    'allowed' => false,
                    'reason' => 'blocked',
                    'message' => 'This device/IP/email has been blocked from using the free plan. Please contact support if you believe this is an error.',
                    'error_code' => 'BLOCKED_IDENTIFIER'
                ];
            }

            // Check for recent attempts
            $maxAttempts = config('free_plan_abuse.max_attempts', 3);
            $trackingDays = config('free_plan_abuse.tracking_period_days', 30);

            if ($this->hasExceededAttempts($ip, $email, $deviceFingerprint, $fingerprintId, $maxAttempts, $trackingDays)) {
                return [
                    'allowed' => false,
                    'reason' => 'too_many_attempts',
                    'message' => 'Too many free plan attempts from this device/IP. Please try again later or contact support.',
                    'error_code' => 'TOO_MANY_ATTEMPTS'
                ];
            }

            return [
                'allowed' => true,
                'message' => 'No abuse patterns detected.'
            ];
        } catch (\Throwable $e) {
            // Never bubble up as system_error to callers that expect specific reasons
            Log::error('checkAbusePatterns failed', ['error' => $e->getMessage()]);
            return [
                'allowed' => true,
                'message' => 'No abuse patterns detected.'
            ];
        }
    }

    /**
     * Check if any identifier is blocked
     */
    private function isBlocked(string $ip, ?string $email, string $deviceFingerprint, string $fingerprintId): bool
    {
        // Check IP
        if (FreePlanAttempt::byIp($ip)->blocked()->exists()) {
            return true;
        }

        // Check device fingerprint
        if (FreePlanAttempt::byDeviceFingerprint($deviceFingerprint)->blocked()->exists()) {
            return true;
        }

        // Check fingerprint ID
        if ($fingerprintId && FreePlanAttempt::byFingerprintId($fingerprintId)->blocked()->exists()) {
            return true;
        }

        // Check email
        if ($email && FreePlanAttempt::byEmail($email)->blocked()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Check if attempts have exceeded the limit
     */
    private function hasExceededAttempts(string $ip, ?string $email, string $deviceFingerprint, string $fingerprintId, int $maxAttempts, int $trackingDays): bool
    {
        $cutoffDate = now()->subDays($trackingDays);

        // Check IP attempts
        $ipAttempts = FreePlanAttempt::byIp($ip)
            ->where('created_at', '>=', $cutoffDate)
            ->count();

        if ($ipAttempts >= $maxAttempts) {
            return true;
        }

        // Check device fingerprint attempts
        $deviceAttempts = FreePlanAttempt::byDeviceFingerprint($deviceFingerprint)
            ->where('created_at', '>=', $cutoffDate)
            ->count();

        if ($deviceAttempts >= $maxAttempts) {
            return true;
        }

        // Check fingerprint ID attempts
        if ($fingerprintId) {
            $fpIdAttempts = FreePlanAttempt::byFingerprintId($fingerprintId)
                ->where('created_at', '>=', $cutoffDate)
                ->count();

            if ($fpIdAttempts >= $maxAttempts) {
                return true;
            }
        }

        // Check email attempts
        if ($email) {
            $emailAttempts = FreePlanAttempt::byEmail($email)
                ->where('created_at', '>=', $cutoffDate)
                ->count();

            if ($emailAttempts >= $maxAttempts) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a free plan attempt
     */
    public function recordAttempt(Request $request, ?User $user = null): void
    {
        // If abuse prevention is disabled, don't record attempts
        if (!config('free_plan_abuse.enabled', false)) {
            return;
        }

        try {
            if ($this->isPrivilegedUser($request, $user)) {
                return;
            }
            DB::beginTransaction();

            $deviceFingerprint = $this->deviceFingerprintService->generateFingerprint($request);
            $email = $request->input('email') ?? $user?->email;

            // Create free plan attempt record
            $attempt = FreePlanAttempt::create([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_fingerprint' => $deviceFingerprint,
                'fingerprint_id' => $request->cookie('fp_id', ''),
                'email' => $email,
                'is_blocked' => false,
                'data' => [
                    'user_id' => $user?->id,
                    'timestamp' => now()->toISOString(),
                    'request_data' => $request->only(['first_name', 'last_name', 'email']),
                ]
            ]);

            // Update user's device information if user exists
            if ($user) {
                $this->updateUserDeviceInfo($user, $request);
            }

            DB::commit();

            Log::info('Free plan attempt recorded', [
                'attempt_id' => $attempt->id,
                'user_id' => $user?->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record free plan attempt', [
                'user_id' => $user?->id,
                'email' => $email ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update user's device information
     */
    private function updateUserDeviceInfo(User $user, Request $request): void
    {
        $deviceFingerprint = $this->deviceFingerprintService->generateFingerprint($request);
        $hashedUserAgent = hash('sha256', $request->userAgent() ?? '');

        $user->update([
            'last_ip' => $request->ip(),
            'device_id' => $hashedUserAgent,
            'last_device_fingerprint' => $deviceFingerprint,
            'last_login_at' => now()
        ]);
    }

    /**
     * Assign free plan to user
     */
    public function assignFreePlan(User $user, Request $request): array
    {
        try {
            // Final check before assignment
            $eligibilityCheck = $this->canUseFreePlan($user, $request);
            if (!$eligibilityCheck['allowed']) {
                return [
                    'success' => false,
                    'reason' => $eligibilityCheck['reason'] ?? 'not_allowed',
                    'message' => $eligibilityCheck['message'] ?? 'Not allowed to use free plan',
                    'error_code' => $eligibilityCheck['error_code'] ?? 'NOT_ALLOWED',
                ];
            }

            DB::beginTransaction();

            // Get or create Free package
            $freePackage = Package::firstOrCreate(
                ['name' => 'Free'],
                ['price' => 0, 'duration' => 'lifetime', 'features' => json_encode([])]
            );

            // Assign free package to user
            $user->update([
                'package_id' => $freePackage->id,
                'is_subscribed' => true,
                'last_ip' => $request->ip(),
                'device_id' => hash('sha256', $request->userAgent() ?? ''),
                'last_device_fingerprint' => $this->deviceFingerprintService->generateFingerprint($request),
                'last_login_at' => now(),
                'has_used_free_plan' => true,
                'free_plan_used_at' => now(),
            ]);

            // Create order record for free plan
            $user->orders()->create([
                'package_id' => $freePackage->id,
                'amount' => 0,
                'status' => 'completed',
                'transaction_id' => 'free_' . uniqid(),
                'metadata' => ['source' => 'free_plan_assignment'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Record the attempt
            $this->recordAttempt($request, $user);

            // Immediately block current identifiers to prevent repeat free plan in same environment (only if enabled)
            if (config('free_plan_abuse.enabled', false)) {
                try {
                    $ip = $request->ip();
                    $email = $user->email;
                    $deviceFingerprint = $this->deviceFingerprintService->generateFingerprint($request);
                    $fingerprintId = $request->cookie('fp_id', '');

                    if ($ip) {
                        $this->blockIdentifier('ip', $ip, 'Auto-block after free plan assignment');
                    }
                    if ($email) {
                        $this->blockIdentifier('email', $email, 'Auto-block after free plan assignment');
                    }
                    if ($deviceFingerprint) {
                        $this->blockIdentifier('device_fingerprint', $deviceFingerprint, 'Auto-block after free plan assignment');
                    }
                    if ($fingerprintId) {
                        $this->blockIdentifier('fingerprint_id', $fingerprintId, 'Auto-block after free plan assignment');
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to auto-block identifiers after free plan assignment', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info('Free plan assigned successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return [
                'success' => true,
                'message' => 'Free plan has been assigned successfully!',
                'package' => $freePackage
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to assign free plan', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to assign free plan. Please try again or contact support.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user can downgrade to free plan
     */
    public function canDowngradeToFree(User $user): array
    {
        // If abuse prevention is disabled, allow downgrade
        if (!config('free_plan_abuse.enabled', false)) {
            return [
                'allowed' => true,
                'message' => 'You can downgrade to the free plan.'
            ];
        }

        if ($this->hasUsedFreePlan($user)) {
            return [
                'allowed' => false,
                'reason' => 'already_used',
                'message' => 'You have exceeded your limit to use the Free plan. Please buy a plan.',
                'error_code' => 'FREE_PLAN_ALREADY_USED'
            ];
        }

        return [
            'allowed' => true,
            'message' => 'You can downgrade to the free plan.'
        ];
    }

    /**
     * Block an identifier (IP, device, email)
     */
    public function blockIdentifier(string $type, string $value, string $reason = 'Manual block'): bool
    {
        try {
            $query = FreePlanAttempt::query();

            switch ($type) {
                case 'ip':
                    $query->byIp($value);
                    break;
                case 'email':
                    $query->byEmail($value);
                    break;
                case 'device_fingerprint':
                    $query->byDeviceFingerprint($value);
                    break;
                case 'fingerprint_id':
                    $query->byFingerprintId($value);
                    break;
                default:
                    return false;
            }

            $attempts = $query->get();
            if ($attempts->isEmpty()) {
                // Create a minimal attempt row for this identifier to reflect block state in tests
                $data = [
                    'ip_address' => $type === 'ip' ? $value : null,
                    'user_agent' => 'system',
                    'device_fingerprint' => $type === 'device_fingerprint' ? $value : '',
                    'fingerprint_id' => $type === 'fingerprint_id' ? $value : '',
                    'email' => $type === 'email' ? $value : null,
                    'is_blocked' => true,
                    'blocked_at' => now(),
                    'block_reason' => $reason,
                ];
                FreePlanAttempt::create($data);
                Log::info('Created placeholder attempt for blocking', ['type' => $type, 'value' => $value]);
                return true;
            }
            $blockedCount = 0;

            foreach ($attempts as $attempt) {
                $attempt->block($reason);
                $blockedCount++;
            }

            Log::info('Identifier blocked', [
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'blocked_count' => $blockedCount
            ]);

            return $blockedCount > 0;

        } catch (\Exception $e) {
            Log::error('Failed to block identifier', [
                'type' => $type,
                'value' => $value,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Unblock an identifier
     */
    public function unblockIdentifier(string $type, string $value): bool
    {
        try {
            $query = FreePlanAttempt::query();

            switch ($type) {
                case 'ip':
                    $query->byIp($value);
                    break;
                case 'email':
                    $query->byEmail($value);
                    break;
                case 'device_fingerprint':
                    $query->byDeviceFingerprint($value);
                    break;
                case 'fingerprint_id':
                    $query->byFingerprintId($value);
                    break;
                default:
                    return false;
            }

            $attempts = $query->blocked()->get();
            $unblockedCount = 0;

            foreach ($attempts as $attempt) {
                $attempt->unblock();
                $unblockedCount++;
            }

            Log::info('Identifier unblocked', [
                'type' => $type,
                'value' => $value,
                'unblocked_count' => $unblockedCount
            ]);

            return $unblockedCount > 0;

        } catch (\Exception $e) {
            Log::error('Failed to unblock identifier', [
                'type' => $type,
                'value' => $value,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get abuse statistics
     */
    public function getAbuseStatistics(int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        $totalAttempts = FreePlanAttempt::where('created_at', '>=', $cutoffDate)->count();
        $blockedAttempts = FreePlanAttempt::where('created_at', '>=', $cutoffDate)->blocked()->count();
        $uniqueIps = FreePlanAttempt::where('created_at', '>=', $cutoffDate)->distinct('ip_address')->count();
        $uniqueEmails = FreePlanAttempt::where('created_at', '>=', $cutoffDate)->distinct('email')->count();
        $uniqueDevices = FreePlanAttempt::where('created_at', '>=', $cutoffDate)->distinct('device_fingerprint')->count();

        return [
            'period_days' => $days,
            'total_attempts' => $totalAttempts,
            'blocked_attempts' => $blockedAttempts,
            'unique_ips' => $uniqueIps,
            'unique_emails' => $uniqueEmails,
            'unique_devices' => $uniqueDevices,
            'block_rate' => $totalAttempts > 0 ? round(($blockedAttempts / $totalAttempts) * 100, 2) : 0
        ];
    }
}
