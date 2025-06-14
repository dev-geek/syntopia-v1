<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Redirect users after login based on role using Spatie roles.
     */
    protected function authenticated(Request $request, $user)
    {
        // Admin or Super Admin bypass verification and redirect to dashboard
        if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('dashboard');
        }

        // Check verification status for regular users
        if (!$this->isUserVerified($user)) {
            Auth::logout();
            session(['email' => $user->email]);
            return redirect()->route('verification.code')
                ->withErrors('Please verify your email before logging in.');
        }

        // Regular user redirect
        return redirect()->intended(route('profile'));
    }

    /**
     * Check if user is properly verified
     */
    private function isUserVerified($user)
    {
        // User must have status = 1 AND email_verified_at filled
        return ($user->status == 1) && !is_null($user->email_verified_at);
    }

    public function redirectTo()
    {
        $user = Auth::user();

        if ($user) {
            if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                return route('dashboard');
            }

            if (!$this->isUserVerified($user)) {
                return route('verification.code');
            }

            return route('profile');
        }

        return '/';
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on role
        if ($user && $user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('dashboard');
        }

        return redirect('/');
    }

    /**
     * Custom login method with proper verification checks
     */
    public function customLogin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Always store email in session
        session(['email' => $request->email]);

        $credentials = $request->only('email', 'password');

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User does not exist.'],
            ]);
        }

        if ($user->google_id !== null) {
            throw ValidationException::withMessages([
                'email' => ['Password not set! You have signed in with Google.'],
            ]);
        }

        // Check verification status BEFORE authentication for non-admin users
        if (!$user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            if (!$this->isUserVerified($user)) {
                session(['email' => $user->email]);
                return redirect()->route('verification.code')
                    ->withErrors('Please verify your email before logging in.');
            }
        }

        // Attempt login only after all checks
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'password' => ['Password is incorrect.'],
            ]);
        }

        // Final redirect based on role
        $user = Auth::user();
        if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('dashboard');
        }

        return redirect()->intended(route('profile'));
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }
}
