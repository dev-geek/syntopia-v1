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
        // Admin or Super Admin redirect
        if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('admin.index');
        }

        // Check if email verified for non-admin users
        if (!$user->hasAnyRole(['Sub Admin', 'Super Admin']) && !$user->hasVerifiedEmail()) {
            Auth::logout();
            return redirect()->route('verification.code')->withErrors('Please verify your email before logging in.');
        }

        // Regular user redirect
        return redirect()->intended(route('profile'));
    }

    /**
     * Optional: override redirectTo for middleware or other redirections.
     * Since we use authenticated(), this may not be necessary.
     */
    public function redirectTo()
    {
        $user = Auth::user();

        if ($user) {
            if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                return route('admin.index');
            }

            if (!$user->hasVerifiedEmail()) {
                Auth::logout();
                // You cannot redirect from here easily, so fallback to home with error
                return route('login')->withErrors('Please verify your email before logging in.');
            }

            return route('profile');
        }

        return '/';
    }

    /**
     * Constructor applies guest middleware except logout.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Logout the user and redirect appropriately.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on role
        if ($user && $user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('admin-login'); // Adjust if you want admin login page
        }

        return redirect('/'); // Default redirect
    }

    /**
     * Custom login method for regular users.
     */
    public function customLogin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

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

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'password' => ['Password is incorrect.'],
            ]);
        }

        // After successful login
        $user = Auth::user();

        if ($user->status == 0) {
            return redirect()->route('verify.code')->withErrors('Verify your account first.');
        }

        if (is_null($user->email_verified_at)) {
            return redirect()->route('verification.code')
                            ->withErrors('Please verify your email before logging in.')
                            ->with('email', $request->email);

        }

        return redirect()->intended(route('profile'));
    }

    /**
     * Check if an email exists in the database (AJAX).
     */
    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }
}
