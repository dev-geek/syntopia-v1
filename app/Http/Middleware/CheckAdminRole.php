<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        
        
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return redirect()->route('admin-login'); // Redirect to admin-login if the user is not logged in
        }

        // If the user is logged in but does not have the 'admin' role, redirect them to the login page
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('admin-login'); // Redirect to admin-login if the user doesn't have the 'admin' role
        }

        return $next($request); // Allow the request to continue if the user is authenticated and has the 'admin' role
    }

}
