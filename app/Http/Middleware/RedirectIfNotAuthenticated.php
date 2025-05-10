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

        $user = Auth::user();     

        if (!Auth::check()) {
            return redirect()->route('admin-login'); // Redirect to admin-login if not logged in
        }
        if (is_null(Auth::user()->role)) {
            return redirect()->back(); // Redirect back if the user doesn't have a role
        }
        
        if ($user->status =='0' || $user->role == '3') {
            // dd($user->role);
            // If the user's status is '0', log them out and invalidate the session
            Auth::logout();  // Log the user out
            $request->session()->invalidate(); // Invalidate the session
            $request->session()->regenerateToken(); // Regenerate the CSRF token

            return redirect()->back()->with('error', 'Your account is deactivated. Please contact support.');

        }

        return $next($request); // Continue with the request if the user is authenticated
    }
}
