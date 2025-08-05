<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeviceFingerprintService
{
    public function generateFingerprint(Request $request): string
    {
        $components = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'accept_encoding' => $request->header('Accept-Encoding'),
            'accept' => $request->header('Accept'),
            'connection' => $request->header('Connection'),
            'upgrade_insecure_requests' => $request->header('Upgrade-Insecure-Requests'),
            'sec_fetch_dest' => $request->header('Sec-Fetch-Dest'),
            'sec_fetch_mode' => $request->header('Sec-Fetch-Mode'),
            'sec_fetch_site' => $request->header('Sec-Fetch-Site'),
            'sec_fetch_user' => $request->header('Sec-Fetch-User'),
        ];

        // Create a hash from the components
        $fingerprint = Hash::make(implode('|', array_filter($components)));
        
        return $fingerprint;
    }

    public function isBlocked(Request $request): bool
    {
        $ip = $request->ip();
        $fingerprint = $this->generateFingerprint($request);
        $email = $request->input('email');

        // Check if IP is blocked
        $ipBlocked = \App\Models\FreePlanAttempt::byIp($ip)
            ->blocked()
            ->exists();

        if ($ipBlocked) {
            return true;
        }

        // Check if device fingerprint is blocked
        $fingerprintBlocked = \App\Models\FreePlanAttempt::byDeviceFingerprint($fingerprint)
            ->blocked()
            ->exists();

        if ($fingerprintBlocked) {
            return true;
        }

        // Check if email is blocked
        if ($email) {
            $emailBlocked = \App\Models\FreePlanAttempt::byEmail($email)
                ->blocked()
                ->exists();

            if ($emailBlocked) {
                return true;
            }
        }

        return false;
    }

    public function hasRecentAttempts(Request $request, int $maxAttempts = 3, int $days = 30): bool
    {
        $ip = $request->ip();
        $fingerprint = $this->generateFingerprint($request);
        $email = $request->input('email');

        // Check IP attempts
        $ipAttempts = \App\Models\FreePlanAttempt::byIp($ip)
            ->recent($days)
            ->count();

        if ($ipAttempts >= $maxAttempts) {
            return true;
        }

        // Check device fingerprint attempts
        $fingerprintAttempts = \App\Models\FreePlanAttempt::byDeviceFingerprint($fingerprint)
            ->recent($days)
            ->count();

        if ($fingerprintAttempts >= $maxAttempts) {
            return true;
        }

        // Check email attempts
        if ($email) {
            $emailAttempts = \App\Models\FreePlanAttempt::byEmail($email)
                ->recent($days)
                ->count();

            if ($emailAttempts >= $maxAttempts) {
                return true;
            }
        }

        return false;
    }

    public function recordAttempt(Request $request): void
    {
        $fingerprint = $this->generateFingerprint($request);
        $email = $request->input('email');

        \App\Models\FreePlanAttempt::create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_fingerprint' => $fingerprint,
            'email' => $email,
        ]);
    }

    public function shouldBlock(Request $request, int $maxAttempts = 3, int $days = 30): bool
    {
        $ip = $request->ip();
        $fingerprint = $this->generateFingerprint($request);
        $email = $request->input('email');

        // Check if any identifier has exceeded the limit
        $ipAttempts = \App\Models\FreePlanAttempt::byIp($ip)->recent($days)->count();
        $fingerprintAttempts = \App\Models\FreePlanAttempt::byDeviceFingerprint($fingerprint)->recent($days)->count();
        $emailAttempts = $email ? \App\Models\FreePlanAttempt::byEmail($email)->recent($days)->count() : 0;

        return $ipAttempts >= $maxAttempts || $fingerprintAttempts >= $maxAttempts || $emailAttempts >= $maxAttempts;
    }
} 