<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated and has the specified role
        if (Auth::check() && Auth::user()->role == 1) {
            return $next($request); // Allow the request to proceed if the role matches
        }

        // Redirect to a different page if the user does not have the required role
        return redirect()->back()->with('success', 'your message,here');   

        // return $next($request);
    }
}
