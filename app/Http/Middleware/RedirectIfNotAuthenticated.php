<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
    if (!Auth::check()) {
            return redirect()->route('admin-login');
        }

        $user = Auth::user();

        // Check if the user has any role assigned using Spatie
        if (!$user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Check if the user's account is deactivated
        if ($user->status == '0') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->back()->with('error', 'Your account is deactivated. Please contact support.');
        }

        // Sub Admin active status is already checked in LoginController
        // No need to duplicate the check here

        return $next($request);
    }
}
