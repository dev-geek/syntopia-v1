<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // Redirect based on user role
                if ($user->hasRole('Super Admin')) {
                    // Clear any intended URLs that might redirect to user routes
                    session()->forget('url.intended');
                    session()->forget('verification_intended_url');
                    return redirect()->route('admin.profile');
                }

                // Default redirect for regular users
                // Clear any intended URLs that might redirect to admin routes
                session()->forget('url.intended');
                session()->forget('verification_intended_url');
                return redirect()->route('user.profile');
            }
        }

        return $next($request);
    }
}
