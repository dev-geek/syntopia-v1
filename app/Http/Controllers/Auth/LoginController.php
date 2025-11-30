<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Package;
use App\Services\DeviceFingerprintService;
use App\Services\SubscriptionService;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    private DeviceFingerprintService $deviceFingerprintService;
    private SubscriptionService $subscriptionService;

    public function __construct(DeviceFingerprintService $deviceFingerprintService, SubscriptionService $subscriptionService)
    {
        $this->deviceFingerprintService = $deviceFingerprintService;
        $this->subscriptionService = $subscriptionService;
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Redirect users after login based on role using Spatie roles.
     */
    protected function authenticated(Request $request, $user)
    {
        // Record device information for all successful logins
        $this->deviceFingerprintService->recordUserDeviceInfo($user, $request);

        // Check verification status for all users
        if (!$this->isUserVerified($user)) {
            Auth::logout();
            session(['email' => $user->email]);
            return redirect()->route('verification.notice')
                ->withErrors('Please verify your email before logging in.');
        }

        // If user came from pricing page, force User routes even for Super Admin
        $fromPricingPage = session('from_pricing_page', false);
        if ($fromPricingPage) {
            session()->forget('from_pricing_page');

            // Clear any admin route intended URLs
            if (session()->has('url.intended')) {
                $intendedUrl = session('url.intended');
                if (str_starts_with($intendedUrl, '/admin') || str_contains($intendedUrl, '/admin/')) {
                    session()->forget('url.intended');
                }
            }
            session()->forget('verification_intended_url');

            // Force User route redirect
            if ($user->hasRole('User')) {
                if ($this->hasActiveSubscription($user)) {
                    return redirect()->route('user.dashboard');
                } else {
                    return redirect()->route('subscription');
                }
            }
            // If somehow not a User role but came from pricing, still redirect to user routes
            return redirect()->route('user.dashboard');
        }

        // Redirect based on user role
        if ($user->hasRole('Super Admin')) {
            return redirect()->intended(route('admin.dashboard'));
        }

        // Check if Sub Admin is active
        if ($user->hasRole('Sub Admin')) {
            if (!$user->canSubAdminLogin()) {
                Auth::logout();
                return redirect()->route('admin-login')->with('error', 'Your account is not active. Please contact support to activate your account.');
            }
            return redirect()->intended(route('admin.dashboard'));
        }

                // For regular users, check subscription status
        if ($user->hasRole('User')) {
            // Clear any admin route intended URLs - regular users should never go to admin routes
            if (session()->has('url.intended')) {
                $intendedUrl = session('url.intended');
                // Only use intended URL if it's NOT an admin route
                if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                    session()->forget('url.intended');
                    return redirect()->to($intendedUrl);
                } else {
                    // Clear admin route intended URLs
                    session()->forget('url.intended');
                }
            }

            // Also check for verification intended URL (but filter out admin routes)
            if (session()->has('verification_intended_url')) {
                $intendedUrl = session('verification_intended_url');
                // Only use intended URL if it's NOT an admin route
                if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                    session()->forget('verification_intended_url');
                    return redirect()->to($intendedUrl);
                } else {
                    // Clear admin route intended URLs
                    session()->forget('verification_intended_url');
                }
            }

            // Clear any remaining intended URLs before redirecting
            session()->forget('url.intended');
            session()->forget('verification_intended_url');

            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard');
            } else {
                return redirect()->route('subscription');
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
            // If user came from pricing page, force User routes even for Super Admin
            $fromPricingPage = session('from_pricing_page', false);
            if ($fromPricingPage) {
                session()->forget('from_pricing_page');
                session()->forget('url.intended');
                session()->forget('verification_intended_url');

                if ($user->hasRole('User')) {
                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard');
                    } else {
                        return redirect()->route('subscription');
                    }
                }
                return redirect()->route('user.dashboard');
            }

            if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
                return redirect()->route('admin.dashboard');
            }

            if (!$this->isUserVerified($user)) {
                return redirect()->route('verification.notice');
            }

            // For regular users, check subscription status
            if ($user->hasRole('User')) {
                // Check for intended URL first (but filter out admin routes)
                if (session()->has('url.intended')) {
                    $intendedUrl = session('url.intended');
                    // Only use intended URL if it's NOT an admin route
                    if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                        session()->forget('url.intended');
                        return redirect()->to($intendedUrl);
                    } else {
                        // Clear admin route intended URLs
                        session()->forget('url.intended');
                    }
                }

                // Clear any remaining intended URLs before redirecting
                session()->forget('url.intended');
                session()->forget('verification_intended_url');

                if ($this->hasActiveSubscription($user)) {
                    return redirect()->route('user.dashboard');
                } else {
                    return redirect()->route('subscription');
                }
            }
        }

        return '/';
    }


    /**
     * Show the application's login form.
     */
    public function showLoginForm(Request $request)
    {
        // Check if user came from pricing page
        $referrer = $request->header('referer');
        if ($referrer && str_contains($referrer, 'syntopia.ai/pricing')) {
            session(['from_pricing_page' => true]);
        }

        // Check if there's an email in the session from a previous login attempt
        $email = $request->session()->get('email');

        // If email exists and belongs to a Super Admin, but user came from pricing page, don't redirect to admin login
        if ($email && !session('from_pricing_page')) {
            $user = User::where('email', $email)->first();
            if ($user && $user->hasRole('Super Admin')) {
                return redirect()->route('admin-login');
            }
        }

        return view('auth.login');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on role
        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
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

        // Check if the email belongs to a Super Admin or Sub Admin
        // But only redirect to admin login if NOT coming from pricing page
        $fromPricingPage = session('from_pricing_page', false);
        $user = User::where('email', $request->email)->first();
        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin']) && !$fromPricingPage) {
            // Store email in session and redirect to admin login
            session(['email' => $request->email]);
            return redirect()->route('admin-login');
        }

        // For non-Super Admin users, proceed with normal login
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
        if (!$user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
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

        // If user came from pricing page, force User routes even for Super Admin
        $fromPricingPage = session('from_pricing_page', false);
        if ($fromPricingPage) {
            session()->forget('from_pricing_page');

            // Clear any admin route intended URLs
            session()->forget('url.intended');
            session()->forget('verification_intended_url');

            // Force User route redirect
            if ($user->hasRole('User')) {
                $this->ensureDefaultFreePlan($user);
                if ($this->hasActiveSubscription($user)) {
                    return redirect()->route('user.dashboard');
                } else {
                    return redirect()->route('subscription');
                }
            }
            // If somehow not a User role but came from pricing, still redirect to user routes
            return redirect()->route('user.dashboard');
        }

        if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return redirect()->intended(route('admin.dashboard'));
        }

        // For regular users, check subscription status
        if ($user->hasRole('User')) {
            // Ensure default free plan + license for existing users without an active license
            $this->ensureDefaultFreePlan($user);

            // Check for intended URL first (but filter out admin routes)
            if (session()->has('url.intended')) {
                $intendedUrl = session('url.intended');
                // Only use intended URL if it's NOT an admin route
                if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                    session()->forget('url.intended');
                    return redirect()->to($intendedUrl);
                } else {
                    // Clear admin route intended URLs
                    session()->forget('url.intended');
                }
            }

            // Clear any remaining intended URLs before redirecting
            session()->forget('url.intended');
            session()->forget('verification_intended_url');

            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard');
            } else {
                return redirect()->route('subscription');
            }
        }

        return redirect()->route('user.profile');
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Ensure a verified regular user has the default Free package with an active license.
     * This is mainly for legacy users created before automatic Free license assignment.
     * Only assigns Free package if user hasn't purchased paid packages.
     */
    private function ensureDefaultFreePlan(User $user): void
    {
        try {
            if (!$user->hasRole('User')) {
                return;
            }

            $user->refresh();
            $user->load('package', 'userLicence');

            // Only proceed if user has tenant_id (required for license API)
            if (!$user->tenant_id) {
                return;
            }

            // If user already has an active license (any package), do nothing
            $activeLicense = $user->userLicence;
            if ($activeLicense && $activeLicense->isActive() && !$activeLicense->isExpired()) {
                return;
            }

            // Check if user has ever purchased a paid package
            $hasPaidPackageOrder = $user->orders()
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->whereHas('package', function ($query) {
                    $query->whereRaw('LOWER(name) != ?', ['free']);
                })
                ->exists();

            // Don't assign Free package if user has purchased paid packages
            if ($hasPaidPackageOrder) {
                Log::info('Skipping Free package assignment - user has purchased paid packages', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            // Check if user has a paid package assigned (package exists and name != 'free')
            if ($user->package && strtolower($user->package->name) !== 'free') {
                Log::info('Skipping Free package assignment - user has paid package assigned', [
                    'user_id' => $user->id,
                    'package_name' => $user->package->name,
                ]);
                return;
            }

            // Find Free package (by price 0 or name free)
            $freePackage = Package::where(function ($query) {
                $query->where('price', 0)
                    ->orWhereRaw('LOWER(name) = ?', ['free']);
            })->first();

            if (!$freePackage) {
                Log::warning('Free package not found when ensuring default plan on login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            $result = $this->subscriptionService->assignFreePlanImmediately($user, $freePackage);

            Log::info('Default free plan ensured on login', [
                'user_id' => $user->id,
                'package_id' => $freePackage->id,
                'license_id' => $result['license_id'] ?? null,
                'order_id' => $result['order_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to ensure default free plan on login', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
