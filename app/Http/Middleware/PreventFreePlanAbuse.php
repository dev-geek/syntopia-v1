<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DeviceFingerprintService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PreventFreePlanAbuse
{
    private DeviceFingerprintService $deviceFingerprintService;

    public function __construct(DeviceFingerprintService $deviceFingerprintService)
    {
        $this->deviceFingerprintService = $deviceFingerprintService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to registration routes
        if (!$request->is('register') || !$request->isMethod('POST')) {
            return $next($request);
        }

        $ip = $request->ip();
        $email = $request->input('email');

        Log::info('Checking free plan abuse prevention', [
            'ip' => $ip,
            'email' => $email,
            'user_agent' => $request->userAgent(),
        ]);

        // Check if already blocked
        if ($this->deviceFingerprintService->isBlocked($request)) {
            Log::warning('Registration blocked - device/IP/email already blocked', [
                'ip' => $ip,
                'email' => $email,
            ]);

            return redirect()->back()
                ->withErrors(['email' => config('free_plan_abuse.messages.device_blocked', 'Registration is not allowed from this device. Please contact support if you believe this is an error.')])
                ->withInput();
        }

        // Check if exceeded attempts
        $maxAttempts = config('free_plan_abuse.max_attempts', 3);
        $trackingDays = config('free_plan_abuse.tracking_period_days', 30);
        
        if ($this->deviceFingerprintService->hasRecentAttempts($request, $maxAttempts, $trackingDays)) {
            Log::warning('Registration blocked - exceeded maximum attempts', [
                'ip' => $ip,
                'email' => $email,
                'max_attempts' => $maxAttempts,
                'tracking_days' => $trackingDays,
            ]);

            // Block the device/IP/email
            $this->deviceFingerprintService->recordAttempt($request);
            
            // Find and block the recent attempts
            $fingerprint = $this->deviceFingerprintService->generateFingerprint($request);
            
            \App\Models\FreePlanAttempt::byIp($ip)->update(['is_blocked' => true, 'blocked_at' => now()]);
            \App\Models\FreePlanAttempt::byDeviceFingerprint($fingerprint)->update(['is_blocked' => true, 'blocked_at' => now()]);
            if ($email) {
                \App\Models\FreePlanAttempt::byEmail($email)->update(['is_blocked' => true, 'blocked_at' => now()]);
            }

            return redirect()->back()
                ->withErrors(['email' => config('free_plan_abuse.messages.too_many_attempts', 'Too many registration attempts from this device. Please contact support if you need assistance.')])
                ->withInput();
        }

        // Record the attempt
        $this->deviceFingerprintService->recordAttempt($request);

        Log::info('Registration attempt recorded', [
            'ip' => $ip,
            'email' => $email,
        ]);

        return $next($request);
    }
} 