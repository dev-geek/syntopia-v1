<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DeviceFingerprintService;
use App\Services\FreePlanAbuseService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PreventFreePlanAbuse
{
    private DeviceFingerprintService $deviceFingerprintService;
    private FreePlanAbuseService $freePlanAbuseService;

    public function __construct(DeviceFingerprintService $deviceFingerprintService, FreePlanAbuseService $freePlanAbuseService)
    {
        $this->deviceFingerprintService = $deviceFingerprintService;
        $this->freePlanAbuseService = $freePlanAbuseService;
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

        // Use the new FreePlanAbuseService for comprehensive checks
        $abuseCheck = $this->freePlanAbuseService->checkAbusePatterns($request);

        if (!$abuseCheck['allowed']) {
            Log::warning('Registration blocked due to abuse patterns', [
                'reason' => $abuseCheck['reason'],
                'ip' => $ip,
                'email' => $email,
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->back()
                ->withErrors(['email' => $abuseCheck['message']])
                ->withInput();
        }

        // Record the attempt for tracking
        try {
            $this->deviceFingerprintService->recordAttempt($request);
        } catch (\Exception $e) {
            Log::error('Failed to record registration attempt', [
                'ip' => $ip,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }

        return $next($request);
    }
}
