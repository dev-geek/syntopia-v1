<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MailService;
use App\Services\TenantAssignmentService;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/user/dashboard';

    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('throttle:6,1')->only('verifyCode', 'resend');
    }

    public function show()
    {
        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    public function verifyCode(Request $request, \App\Services\SubscriptionService $subscriptionService, TenantAssignmentService $tenantAssignmentService)
    {
        Log::info('[verifyCode] Incoming request', [
            'input' => $request->all(),
            'session_email' => session('email'),
        ]);
        $request->validate([
            'verification_code' => 'required|string|size:6',
            'email' => 'required|email',
        ]);

        $email = session('email');
        if (!$email && $request->has('email')) {
            $email = $request->input('email');
            session(['email' => $email]);
            Log::info('[verifyCode] Set email from request', ['email' => $email]);
        }
        if (!$email) {
            Log::error('[verifyCode] No email in session or request');
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            Log::error('[verifyCode] User not found', ['email' => $email]);
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        // Check if user is already verified - treat as success
        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            Log::info('[verifyCode] User already verified (idempotent operation)', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If user has tenant_id, they're fully set up - just login
            if ($user->tenant_id) {
                session()->forget('email');
                Auth::login($user);

                if ($user->hasRole('User')) {
                    // Check for intended URL (but filter out admin routes)
                    if (session()->has('verification_intended_url')) {
                        $intendedUrl = session('verification_intended_url');
                        // Only use intended URL if it's NOT an admin route
                        if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                            session()->forget('verification_intended_url');
                            return redirect()->to($intendedUrl)
                                ->with('success', 'Email already verified!');
                        } else {
                            // Clear admin route intended URLs
                            session()->forget('verification_intended_url');
                        }
                    }

                    // Clear any remaining intended URLs before redirecting
                    session()->forget('url.intended');
                    session()->forget('verification_intended_url');

                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')
                            ->with('success', 'Email already verified!');
                    } else {
                        return redirect()->route('subscription')
                            ->with('success', 'Email already verified!');
                    }
                }

                if ($user->hasRole('Super Admin')) {
                    return redirect()->route('admin.dashboard')
                        ->with('success', 'Email already verified!');
                }
            }

            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        if ($user->verification_code !== $request->verification_code) {
            Log::error('[verifyCode] Invalid verification code', [
                'user_id' => $user->id,
                'expected' => $user->verification_code,
                'provided' => $request->verification_code
            ]);
            return back()->withErrors(['verification_code' => 'Invalid verification code.']);
        }

        if (!$user->subscriber_password) {
            Log::error('[verifyCode] No subscriber_password found for user during verification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return back()->withErrors([
                'server_error' => 'Password data missing. Please contact support.'
            ]);
        }

        try {
            DB::beginTransaction();

            // Check if user already has a tenant_id (from a previous partial registration)
            if ($user->tenant_id) {
                Log::info('[verifyCode] User already has tenant_id, skipping tenant creation', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id
                ]);

                // Just verify the email and update status
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 1,
                    'verification_code' => null,
                ]);

                DB::commit();
                session()->forget('email');
                Auth::login($user);

                if ($user->hasRole('User')) {
                    // Check for intended URL (but filter out admin routes)
                    if (session()->has('verification_intended_url')) {
                        $intendedUrl = session('verification_intended_url');
                        // Only use intended URL if it's NOT an admin route
                        if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                            session()->forget('verification_intended_url');
                            return redirect()->to($intendedUrl)
                                ->with('success', 'Email verified successfully!');
                        } else {
                            // Clear admin route intended URLs
                            session()->forget('verification_intended_url');
                        }
                    }

                    // Clear any remaining intended URLs before redirecting
                    session()->forget('url.intended');
                    session()->forget('verification_intended_url');

                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')
                            ->with('success', 'Email verified successfully!');
                    } else {
                        return redirect()->route('subscription')
                            ->with('success', 'Email verified successfully!');
                    }
                }

                if ($user->hasRole('Super Admin')) {
                    return redirect()->route('admin.dashboard')
                        ->with('success', 'Email verified successfully!');
                }

                return redirect()->route('login')
                    ->with('success', 'Email verified successfully! You can now login.');
            }

            Log::info('[verifyCode] Calling TenantAssignmentService', ['user_id' => $user->id]);
            $apiResponse = $tenantAssignmentService->assignTenant($user);
            Log::info('[verifyCode] TenantAssignmentService response', ['user_id' => $user->id, 'apiResponse' => $apiResponse]);

            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                Log::error('[verifyCode] API returned swal error', ['user_id' => $user->id, 'error' => $apiResponse['error_message']]);

                // Check if error is due to user already being registered in tenant system
                if (str_contains($apiResponse['error_message'] ?? '', 'already registered')) {
                    // Check if there's another user with same email that has tenant_id
                    $existingUserWithTenant = User::where('email', $user->email)
                        ->whereNotNull('tenant_id')
                        ->where('id', '!=', $user->id)
                        ->first();

                    if ($existingUserWithTenant) {
                        Log::warning('[verifyCode] Duplicate user detected with existing tenant', [
                            'current_user_id' => $user->id,
                            'existing_user_id' => $existingUserWithTenant->id,
                            'tenant_id' => $existingUserWithTenant->tenant_id
                        ]);

                        // Delete the duplicate user
                        $user->delete();
                        return redirect()->route('login')
                            ->with('error', 'An account with this email already exists. Please login instead.');
                    }
                }

                // Update user verification status even if tenant assignment failed - will retry later
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 1,
                    'verification_code' => null,
                ]);
                DB::commit();

                return back()->with('verification_swal_error', $apiResponse['error_message']);
            }

            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                Log::error('[verifyCode] Tenant assignment failed or missing tenantId - will retry later', [
                    'user_id' => $user->id,
                    'apiResponse' => $apiResponse
                ]);

                // Update user verification status even if tenant assignment failed - will retry later
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 1,
                    'verification_code' => null,
                ]);
                DB::commit();

                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                return redirect()->route('login')->with('error', $errorMsg . ' Your account has been created and tenant assignment will be retried automatically.');
            }

            $user->update([
                'email_verified_at' => now(),
                'status' => 1,
                'verification_code' => null,
            ]);

            // After tenant is created and password bound, assign Free package with license
            // Only assign if user hasn't purchased paid packages
            $user->refresh();
            $user->load('package', 'userLicence', 'orders');

            // Check if user has ever purchased a paid package
            $hasPaidPackageOrder = $user->orders()
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->whereHas('package', function ($query) {
                    $query->whereRaw('LOWER(name) != ?', ['free']);
                })
                ->exists();

            // Only assign Free package if user hasn't purchased paid packages
            if (!$hasPaidPackageOrder) {
                // Check if user has a paid package assigned (package exists and name != 'free')
                $hasPaidPackage = $user->package && strtolower($user->package->name) !== 'free';

                if (!$hasPaidPackage) {
                    $freePackage = \App\Models\Package::where(function ($query) {
                        $query->where('price', 0)
                            ->orWhereRaw('LOWER(name) = ?', ['free']);
                    })->first();

                    if (!$freePackage) {
                        Log::error('[verifyCode] Free package not found during verification', [
                            'user_id' => $user->id,
                            'tenant_id' => $user->tenant_id,
                        ]);
                        throw new \Exception('Free package is not configured. Please contact support.');
                    }

                    $subscriptionService->assignFreePlanImmediately($user, $freePackage);
                    Log::info('[verifyCode] Free package assigned during email verification', ['user_id' => $user->id]);
                } else {
                    Log::info('[verifyCode] Skipping Free package assignment - user has paid package', [
                        'user_id' => $user->id,
                        'package_name' => $user->package->name,
                    ]);
                }
            } else {
                Log::info('[verifyCode] Skipping Free package assignment - user has purchased paid packages', [
                    'user_id' => $user->id,
                ]);
            }

            DB::commit();
            Log::info('[verifyCode] User verified and updated', ['user_id' => $user->id]);

            session()->forget('email');
            Auth::login($user);

            if ($user->hasRole('User')) {
                // Check if there's an intended URL from the subscription flow (but filter out admin routes)
                if (session()->has('verification_intended_url')) {
                    $intendedUrl = session('verification_intended_url');
                    // Only use intended URL if it's NOT an admin route
                    if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                        session()->forget('verification_intended_url');
                        Log::info('[verifyCode] Redirecting to intended URL', [
                            'user_id' => $user->id,
                            'intended_url' => $intendedUrl
                        ]);
                        return redirect()->to($intendedUrl)
                            ->with('success', 'Email verified successfully!');
                    } else {
                        // Clear admin route intended URLs
                        session()->forget('verification_intended_url');
                    }
                }

                // Also check for regular intended URL (but filter out admin routes)
                if (session()->has('url.intended')) {
                    $intendedUrl = session('url.intended');
                    // Only use intended URL if it's NOT an admin route
                    if (!str_starts_with($intendedUrl, '/admin') && !str_contains($intendedUrl, '/admin/')) {
                        session()->forget('url.intended');
                        Log::info('[verifyCode] Redirecting to intended URL', [
                            'user_id' => $user->id,
                            'intended_url' => $intendedUrl
                        ]);
                        return redirect()->to($intendedUrl)
                            ->with('success', 'Email verified successfully!');
                    } else {
                        // Clear admin route intended URLs
                        session()->forget('url.intended');
                    }
                }

                // Clear any remaining intended URLs before redirecting
                session()->forget('url.intended');
                session()->forget('verification_intended_url');

                // Check if user has active subscription
                if ($this->hasActiveSubscription($user)) {
                    Log::info('[verifyCode] Redirecting to user dashboard', ['user_id' => $user->id]);
                    return redirect()->route('user.dashboard')
                        ->with('success', 'Email verified successfully!');
                } else {
                    Log::info('[verifyCode] Redirecting to subscription', ['user_id' => $user->id]);
                    return redirect()->route('subscription')
                        ->with('success', 'Email verified successfully!');
                }
            }
            if ($user->hasRole('Super Admin')) {
                Log::info('[verifyCode] Redirecting to admin.dashboard', ['user_id' => $user->id]);
                return redirect()->route('admin.dashboard')
                    ->with('success', 'Email verified successfully!');
            }
            Log::info('[verifyCode] Redirecting to login (fallback)', ['user_id' => $user->id]);
            return redirect()->route('login')
                ->with('success', 'Email verified successfully! You can now login.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[verifyCode] Exception during verification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            if (isset($user)) {
                $user->delete(); // Delete user data on failure
            }
            return redirect()->route('login')->withErrors([
                'server_error' => 'Something went wrong during verification. Please try again.'
            ]);
        }
    }

    public function resend()
    {
        $user = User::where('email', session('email'))->first();

        if ($user) {
            $user->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->save();

            // Send verification email with proper error handling
            $mailResult = MailService::send($user->email, new VerifyEmail($user));

            if ($mailResult['success']) {
                Log::info('Verification email resent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return back()->with('message', 'Verification code has been resent');
            } else {
                Log::error('Failed to resend verification email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailResult['error'] ?? 'Unknown error'
                ]);

                // Store the verification code in session as fallback
                session(['verification_code' => $user->verification_code]);
                return back()->withErrors(['mail_error' => $mailResult['message']]);
            }
        }

        return back()->withErrors(['mail_error' => 'User not found. Please try logging in again.']);
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

    public function deleteUserAndRedirect(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        if ($user) {
            Log::info('[deleteUserAndRedirect] Deleting user', ['user_id' => $user->id, 'email' => $email]);
            $user->delete();
        } else {
            Log::warning('[deleteUserAndRedirect] User not found', ['email' => $email]);
        }
        session()->forget('email');
        return redirect()->route('login')->with('error', 'Your account was not created. Please register again with a different email.');
    }

    private function handleFailedAddLicense(\Illuminate\Http\Client\Response $response, User $user): array
    {
        $status = $response->status();
        $errorMessage = "[$status] " . match ($status) {
            400 => 'Bad Request - Missing required parameters.',
            401 => 'Unauthorized - Invalid or expired subscription key.',
            404 => 'Not Found - The requested resource does not exist.',
            429 => 'Too Many Requests - Rate limit exceeded.',
            500 => 'Internal Server Error - API server issue.',
            default => 'Unexpected error occurred.'
        };

        Log::error('Failed to add license', [
            'user_id' => $user->id,
            'status' => $status,
            'response' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage,
            'swal' => true
        ];
    }
}
