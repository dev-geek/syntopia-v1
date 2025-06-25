<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DynamicRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $user = $request->user();

        // Different limits for different user types
        if ($user) {
            // Authenticated users get higher limits
            $key = 'api:' . $user->id;
            $maxAttempts = (int) $maxAttempts;
        } else {
            // Anonymous users get stricter limits
            $key = 'api:' . $request->ip();
            $maxAttempts = min((int) $maxAttempts, 20); // Cap at 20 for anonymous
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        RateLimiter::hit($key, (int) $decayMinutes * 60);

        return $next($request);
    }
}
