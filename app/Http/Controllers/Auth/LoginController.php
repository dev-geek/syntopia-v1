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
        // Check verification status for all users
        if (!$this->isUserVerified($user)) {
            Auth::logout();
            session(['email' => $user->email]);
            return redirect()->route('verification.notice')
                ->withErrors('Please verify your email before logging in.');
        }

        // Redirect based on user role
        if ($user->hasRole('Super Admin') || $user->hasRole('Sub Admin')) {
            return redirect()->intended(route('admin.dashboard'));
        }

                // For regular users, check subscription status
        if ($user->hasRole('User')) {
            // Check if there's an intended URL (like subscription page with package)
            if (session()->has('url.intended')) {
                $intendedUrl = session('url.intended');
                session()->forget('url.intended');
                return redirect()->to($intendedUrl);
            }

            // Also check for verification intended URL
            if (session()->has('verification_intended_url')) {
                $intendedUrl = session('verification_intended_url');
                session()->forget('verification_intended_url');
                return redirect()->to($intendedUrl);
            }

            if ($this->hasActiveSubscription($user)) {
                return redirect()->intended(route('user.dashboard'));
            } else {
                return redirect()->intended(route('home'));
            }
        }

        // Default redirect for regular users
        return redirect()->intended(route('user.profile'));
    }

    /**
     * Check if user is properly verified
     */
    private function isUserVerified($user)
    {
        // User must have status = 1 AND email_verified_at filled
        return ($user->status == 1) && !is_null($user->email_verified_at);
    }

    /**
     * Check if user has an active subscription
     */
    private function hasActiveSubscription($user)
    {
        if (!$user->is_subscribed || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        // Check if user has an active license
        $activeLicense = $user->userLicence;
        if (!$activeLicense || !$activeLicense->isActive()) {
            return false;
        }

        // Check if license is not expired
        if ($activeLicense->isExpired()) {
            return false;
        }

        return true;
    }



    public function redirectTo()
    {
        $user = Auth::user();

        if ($user) {
            if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                return redirect()->route('admin.dashboard');
            }

            if (!$this->isUserVerified($user)) {
                return redirect()->route('verification.notice');
            }

            // For regular users, check subscription status
            if ($user->hasRole('User')) {
                // Check for intended URL first
                if (session()->has('url.intended')) {
                    $intendedUrl = session('url.intended');
                    session()->forget('url.intended');
                    return redirect()->to($intendedUrl);
                }

                if ($this->hasActiveSubscription($user)) {
                    return redirect()->route('user.dashboard');
                } else {
                    return redirect()->route('home');
                }
            }
        }

        return '/';
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Show the application's login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on role
        if ($user && $user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('admin-login');
        }

        // Redirect regular users to login page
        return redirect()->route('login');
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

        // if ($user->google_id !== null) {
        //     throw ValidationException::withMessages([
        //         'email' => ['Password not set! You have signed in with Google.'],
        //     ]);
        // }

        // Check verification status BEFORE authentication for non-admin users
        if (!$user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            if (!$this->isUserVerified($user)) {
                session(['email' => $user->email]);
                return redirect()->route('verification.notice')
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
            return redirect()->intended(route('admin.dashboard'));
        }

        // For regular users, check subscription status
        if ($user->hasRole('User')) {
            // Check for intended URL first
            if (session()->has('url.intended')) {
                $intendedUrl = session('url.intended');
                session()->forget('url.intended');
                return redirect()->to($intendedUrl);
            }

            if ($this->hasActiveSubscription($user)) {
                return redirect()->intended(route('user.dashboard'));
            } else {
                return redirect()->intended(route('home'));
            }
        }

        return redirect()->intended(route('user.profile'));
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }
}
