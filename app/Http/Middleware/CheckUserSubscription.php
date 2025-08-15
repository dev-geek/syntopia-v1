<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Skip for guests, admins, and super admins
        if (!$user || $user->hasAnyRole(['Super Admin', 'Sub Admin', 'Admin'])) {
            return $next($request);
        }

        // Check if user has an active subscription
        if (!$user->hasActiveSubscription()) {
            // Redirect to subscription page with a message
            return redirect()->route('subscription.required')
                ->with('error', 'You need an active subscription to access this page.');
        }

        return $next($request);
    }
}
