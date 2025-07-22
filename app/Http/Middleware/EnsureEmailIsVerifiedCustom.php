<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedCustom
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is not authenticated, redirect to login
        if (!$user) {
            return redirect()->route('login');
        }

        // Allow access for admins (bypass verification)
        if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return $next($request);
        }

        // Check verification status for regular users
        // User must have both status = 1 AND email_verified_at filled
        if ($user->status != 1 || is_null($user->email_verified_at)) {
            // Store email in session before logout
            session(['email' => $user->email]);

            // Logout the user
            Auth::logout();

            return redirect()->route('verification.notice')
                ->withErrors('Please verify your email before accessing this page.');
        }

        return $next($request);
    }
}
