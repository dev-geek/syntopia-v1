<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookIPFilter
{
    // Known webhook IP ranges for each gateway
    private array $allowedIPs = [
        'paddle' => [
            // Paddle's webhook IPs
            '34.194.127.46',
            '54.234.237.108',
        ],
        'payproglobal' => [
            // PayProGlobal's webhook IPs
        ],
        'fastspring' => [
            // FastSpring's webhook IPs
        ],
    ];

    public function handle(Request $request, Closure $next, string $gateway = null): Response
    {
        $clientIP = $request->ip();

        // Skip IP filtering in local development
        if (app()->environment('local')) {
            return $next($request);
        }

        // If no gateway specified, try to extract from route
        if (!$gateway && $request->route()) {
            $gateway = $request->route('gateway');
        }

        if ($gateway && isset($this->allowedIPs[$gateway])) {
            $allowedIPs = $this->allowedIPs[$gateway];

            if (!in_array($clientIP, $allowedIPs)) {
                Log::warning('Webhook request from unauthorized IP', [
                    'gateway' => $gateway,
                    'ip' => $clientIP,
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl()
                ]);

                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        return $next($request);
    }
}
