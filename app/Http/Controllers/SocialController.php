<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Services\PasswordBindingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\SubscriptionService;
use App\Models\Package;


class SocialController extends Controller
{
    public function googleLogin()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleAuthentication(PasswordBindingService $passwordBindingService)
    {
        try {
            // Get the user information from Google
            $googleUser = Socialite::driver('google')->user();

            // Check if a user with the Google ID already exists
            $user = User::where('google_id', $googleUser->id)->first();

            if ($user) {
                // Log in the existing user
                Auth::login($user);
                if ($user->hasAnyRole(['Super Admin'])) {
                    return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                }

                // For regular users, check subscription status
                if ($user->hasRole('User')) {
                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')->with('login_success', 'User Login Successfully');
                    } else {
                        return redirect()->route('subscription')->with('login_success', 'User Login Successfully');
                    }
                }

                return redirect()->route('user.profile')->with('login_success', 'User Login Successfully');

            } else {
                // Check if a user with the same email exists
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Try to link Google account with existing user
                    try {
                        DB::beginTransaction();

                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Check if user has tenant_id, if not, create it
                        if (!$existingUser->tenant_id) {
                            Log::info('[googleAuthentication] Existing user missing tenant_id, creating tenant', ['user_id' => $existingUser->id]);
                            $apiResponse = $this->callXiaoiceApiWithCreds($existingUser, $compliantPassword);

                            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                DB::rollBack();
                                Log::error('[googleAuthentication] API returned swal error during tenant creation for existing user', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);
                                return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                            }

                            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                                DB::rollBack();
                                Log::error('[googleAuthentication] Failed to create tenant_id for existing user', [
                                    'user_id' => $existingUser->id,
                                    'apiResponse' => $apiResponse
                                ]);
                                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                                return redirect()->route('login')->with('error', $errorMsg);
                            }

                            // Update user with tenant_id
                            $existingUser->update([
                                'tenant_id' => $apiResponse['data']['tenantId'],
                            ]);
                        } else {
                            // User already has tenant_id, just bind password
                            $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                            if (!$apiResponse['success']) {
                                Log::warning('Failed to bind password for existing user during Google link, proceeding with fallback', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);

                                // Check if this is a SWAL error
                                if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                    DB::rollBack();
                                    return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                                }

                                // Fallback: Link account without updating password
                                $existingUser->update([
                                    'google_id' => $googleUser->id,
                                    'email_verified_at' => Carbon::now(),
                                    'status' => 1
                                ]);

                                DB::commit();
                                Auth::login($existingUser);

                                return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Note: You may need to update your password later for full functionality.');
                            }
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        // Assign Free package with license if user doesn't have a paid package
                        $existingUser->refresh();
                        $existingUser->load('package', 'userLicence');

                        // Check if user has ever purchased a paid package
                        $hasPaidPackageOrder = $existingUser->orders()
                            ->where('status', 'completed')
                            ->where('amount', '>', 0)
                            ->whereHas('package', function ($query) {
                                $query->whereRaw('LOWER(name) != ?', ['free']);
                            })
                            ->exists();

                        // Only assign Free package if:
                        // 1. User has no package OR
                        // 2. User has Free package but no active license
                        // Don't assign if user has a paid package (package exists and name != 'free') OR has purchased paid packages
                        $shouldAssignFree = false;
                        if ($hasPaidPackageOrder) {
                            // User has purchased paid packages - don't assign Free
                            $shouldAssignFree = false;
                        } elseif (!$existingUser->package_id) {
                            // User has no package - assign Free
                            $shouldAssignFree = true;
                        } elseif ($existingUser->package && strtolower($existingUser->package->name) === 'free') {
                            // User has Free package but check if they have active license
                            if (!$existingUser->hasActiveSubscription()) {
                                $shouldAssignFree = true;
                            }
                        }
                        // If user has a paid package (package exists and name != 'free'), don't assign Free

                        if ($shouldAssignFree && $existingUser->tenant_id) {
                            $freePackage = Package::where(function ($query) {
                                $query->where('price', 0)
                                    ->orWhereRaw('LOWER(name) = ?', ['free']);
                            })->first();

                            if ($freePackage) {
                                $subscriptionService = app(SubscriptionService::class);
                                try {
                                    $subscriptionService->assignFreePlanImmediately($existingUser, $freePackage);
                                    Log::info('[googleAuthentication] Free package assigned to existing user after tenant creation', ['user_id' => $existingUser->id]);
                                } catch (\Exception $e) {
                                    Log::warning('[googleAuthentication] Failed to assign Free package to existing user', [
                                        'user_id' => $existingUser->id,
                                        'error' => $e->getMessage()
                                    ]);
                                    // Continue even if Free package assignment fails
                                }
                            }
                        }

                        DB::commit();
                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Google successfully!');

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Error during Google account linking', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback: Link account without password update
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Please update your password in your profile for full functionality.');
                    }
                } else {
                    // Create new user with tenant creation
                    try {
                        DB::beginTransaction();

                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Create user first
                        $userData = User::create([
                            'name' => $googleUser->name,
                            'email' => $googleUser->email,
                            'google_id' => $googleUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => null, // Set to NULL for first-time Google registration
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $userData->assignRole('User');

                        // Create tenant and bind password using the same logic as VerificationController
                        Log::info('[googleAuthentication] Calling callXiaoiceApiWithCreds for new Google user', ['user_id' => $userData->id]);
                        $apiResponse = $this->callXiaoiceApiWithCreds($userData, $compliantPassword);
                        Log::info('[googleAuthentication] callXiaoiceApiWithCreds response', ['user_id' => $userData->id, 'apiResponse' => $apiResponse]);

                        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                            DB::rollBack();
                            Log::error('[googleAuthentication] API returned swal error', ['user_id' => $userData->id, 'error' => $apiResponse['error_message']]);
                            return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                        }

                        if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                            DB::rollBack();
                            Log::error('[googleAuthentication] API failed or missing tenantId', [
                                'user_id' => $userData->id,
                                'apiResponse' => $apiResponse
                            ]);
                            $userData->delete(); // Delete user data on failure
                            $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                            return redirect()->route('login')->with('error', $errorMsg);
                        }

                        // Update user with tenant_id and subscriber_password
                        $userData->update([
                            'tenant_id' => $apiResponse['data']['tenantId'],
                            'subscriber_password' => $compliantPassword,
                        ]);

                        // After tenant is created and password bound, assign Free package with license
                        $freePackage = Package::where(function ($query) {
                            $query->where('price', 0)
                                ->orWhereRaw('LOWER(name) = ?', ['free']);
                        })->first();

                        if (!$freePackage) {
                            Log::error('[googleAuthentication] Free package not found during Google registration', [
                                'user_id' => $userData->id,
                                'tenant_id' => $userData->tenant_id,
                            ]);
                            DB::rollBack();
                            $userData->delete();
                            return redirect()->route('login')->with('error', 'Free package is not configured. Please contact support.');
                        }

                        $subscriptionService = app(SubscriptionService::class);
                        $subscriptionService->assignFreePlanImmediately($userData, $freePackage);

                        DB::commit();
                        Log::info('[googleAuthentication] New Google user created with tenant and Free package', ['user_id' => $userData->id]);

                        Auth::login($userData);
                        return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Google');

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('[googleAuthentication] Exception during new Google user creation', [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email' => $googleUser->email
                        ]);

                        if (isset($userData)) {
                            $userData->delete(); // Delete user data on failure
                        }

                        return redirect()->route('login')->with('error', 'Failed to create account. Please try again or contact support.');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Google Authentication Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'We encountered an issue connecting to Google. Please try again or use email login instead.')
                ->withInput();
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback(PasswordBindingService $passwordBindingService)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();

            // Check if user with Facebook ID exists
            $user = User::where('facebook_id', $facebookUser->id)->first();

            if ($user) {
                Auth::login($user);
                return $this->redirectBasedOnUserRole($user, 'User Login Successfully');
            } else {
                // Check if user with same email exists
                $existingUser = User::where('email', $facebookUser->email)->first();

                if ($existingUser) {
                    // Try to link Facebook account with existing user
                    try {
                        DB::beginTransaction();

                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Check if user has tenant_id, if not, create it
                        if (!$existingUser->tenant_id) {
                            Log::info('[handleFacebookCallback] Existing user missing tenant_id, creating tenant', ['user_id' => $existingUser->id]);
                            $apiResponse = $this->callXiaoiceApiWithCreds($existingUser, $compliantPassword);

                            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                DB::rollBack();
                                Log::error('[handleFacebookCallback] API returned swal error during tenant creation for existing user', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);
                                return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                            }

                            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                                DB::rollBack();
                                Log::error('[handleFacebookCallback] Failed to create tenant_id for existing user', [
                                    'user_id' => $existingUser->id,
                                    'apiResponse' => $apiResponse
                                ]);
                                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                                return redirect()->route('login')->with('error', $errorMsg);
                            }

                            // Update user with tenant_id
                            $existingUser->update([
                                'tenant_id' => $apiResponse['data']['tenantId'],
                            ]);
                        } else {
                            // User already has tenant_id, just bind password
                            $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                            if (!$apiResponse['success']) {
                                Log::warning('Failed to bind password for existing user during Facebook link, proceeding with fallback', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);

                                // Check if this is a SWAL error
                                if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                    DB::rollBack();
                                    return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                                }

                                // Fallback: Link account without updating password
                                $existingUser->update([
                                    'facebook_id' => $facebookUser->id,
                                    'email_verified_at' => Carbon::now(),
                                    'status' => 1
                                ]);

                                DB::commit();
                                Auth::login($existingUser);
                                return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Note: You may need to update your password later for full functionality.');
                            }
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        // Assign Free package with license if user doesn't have a paid package
                        $existingUser->refresh();
                        $existingUser->load('package', 'userLicence');

                        // Check if user has ever purchased a paid package
                        $hasPaidPackageOrder = $existingUser->orders()
                            ->where('status', 'completed')
                            ->where('amount', '>', 0)
                            ->whereHas('package', function ($query) {
                                $query->whereRaw('LOWER(name) != ?', ['free']);
                            })
                            ->exists();

                        // Only assign Free package if:
                        // 1. User has no package OR
                        // 2. User has Free package but no active license
                        // Don't assign if user has a paid package (package exists and name != 'free') OR has purchased paid packages
                        $shouldAssignFree = false;
                        if ($hasPaidPackageOrder) {
                            // User has purchased paid packages - don't assign Free
                            $shouldAssignFree = false;
                        } elseif (!$existingUser->package_id) {
                            // User has no package - assign Free
                            $shouldAssignFree = true;
                        } elseif ($existingUser->package && strtolower($existingUser->package->name) === 'free') {
                            // User has Free package but check if they have active license
                            if (!$existingUser->hasActiveSubscription()) {
                                $shouldAssignFree = true;
                            }
                        }
                        // If user has a paid package (package exists and name != 'free'), don't assign Free

                        if ($shouldAssignFree && $existingUser->tenant_id) {
                            $freePackage = Package::where(function ($query) {
                                $query->where('price', 0)
                                    ->orWhereRaw('LOWER(name) = ?', ['free']);
                            })->first();

                            if ($freePackage) {
                                $subscriptionService = app(SubscriptionService::class);
                                try {
                                    $subscriptionService->assignFreePlanImmediately($existingUser, $freePackage);
                                    Log::info('[handleFacebookCallback] Free package assigned to existing user after tenant creation', ['user_id' => $existingUser->id]);
                                } catch (\Exception $e) {
                                    Log::warning('[handleFacebookCallback] Failed to assign Free package to existing user', [
                                        'user_id' => $existingUser->id,
                                        'error' => $e->getMessage()
                                    ]);
                                    // Continue even if Free package assignment fails
                                }
                            }
                        }

                        DB::commit();
                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Facebook successfully!');

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Error during Facebook account linking', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback: Link account without password update
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Please update your password in your profile for full functionality.');
                    }
                } else {
                    // Create new user with tenant creation
                    try {
                        DB::beginTransaction();

                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Create user first
                        $userData = User::create([
                            'name' => $facebookUser->name,
                            'email' => $facebookUser->email,
                            'facebook_id' => $facebookUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $userData->assignRole('User');

                        // Create tenant and bind password using the same logic as VerificationController
                        Log::info('[handleFacebookCallback] Calling callXiaoiceApiWithCreds for new Facebook user', ['user_id' => $userData->id]);
                        $apiResponse = $this->callXiaoiceApiWithCreds($userData, $compliantPassword);
                        Log::info('[handleFacebookCallback] callXiaoiceApiWithCreds response', ['user_id' => $userData->id, 'apiResponse' => $apiResponse]);

                        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                            DB::rollBack();
                            Log::error('[handleFacebookCallback] API returned swal error', ['user_id' => $userData->id, 'error' => $apiResponse['error_message']]);
                            return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                        }

                        if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                            DB::rollBack();
                            Log::error('[handleFacebookCallback] API failed or missing tenantId', [
                                'user_id' => $userData->id,
                                'apiResponse' => $apiResponse
                            ]);
                            $userData->delete(); // Delete user data on failure
                            $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                            return redirect()->route('login')->with('error', $errorMsg);
                        }

                        // Update user with tenant_id
                        $userData->update([
                            'tenant_id' => $apiResponse['data']['tenantId'],
                        ]);

                        // After tenant is created and password bound, assign Free package with license
                        $freePackage = Package::where(function ($query) {
                            $query->where('price', 0)
                                ->orWhereRaw('LOWER(name) = ?', ['free']);
                        })->first();

                        if (!$freePackage) {
                            Log::error('[handleFacebookCallback] Free package not found during Facebook registration', [
                                'user_id' => $userData->id,
                                'tenant_id' => $userData->tenant_id,
                            ]);
                            DB::rollBack();
                            $userData->delete();
                            return redirect()->route('login')->with('error', 'Free package is not configured. Please contact support.');
                        }

                        $subscriptionService = app(SubscriptionService::class);
                        $subscriptionService->assignFreePlanImmediately($userData, $freePackage);

                        DB::commit();
                        Log::info('[handleFacebookCallback] New Facebook user created with tenant and Free package', ['user_id' => $userData->id]);

                        Auth::login($userData);
                        return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Facebook');

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('[handleFacebookCallback] Exception during new Facebook user creation', [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email' => $facebookUser->email
                        ]);

                        if (isset($userData)) {
                            $userData->delete(); // Delete user data on failure
                        }

                        return redirect()->route('login')->with('error', 'Failed to create account. Please try again or contact support.');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Facebook Authentication Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'We encountered an issue connecting to Facebook. Please try again or use email login instead.')
                ->withInput();
        }
    }

    /**
     * Generate a password that meets Xiaoice API requirements
     */
    private function generateCompliantPassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = ',.<>{}~!@#$%^&_';

        // Ensure at least one character from each required category
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]; // One uppercase
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)]; // One lowercase
        $password .= $numbers[random_int(0, strlen($numbers) - 1)]; // One number
        $password .= $special[random_int(0, strlen($special) - 1)]; // One special

        // Fill the rest with random characters from all categories
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < 12; $i++) { // Total length 12 characters
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to make it more random
        return str_shuffle($password);
    }

    /**
     * Redirect user based on their role and subscription status
     */
    private function redirectBasedOnUserRole($user, $message)
    {
        // Check for intended URL first
        if (session()->has('url.intended')) {
            $intendedUrl = session('url.intended');
            session()->forget('url.intended');
            return redirect()->to($intendedUrl)->with('login_success', $message);
        }

        if ($user->hasAnyRole(['Super Admin'])) {
            return redirect()->route('admin.dashboard')->with('login_success', $message);
        }

        if ($user->hasRole('User')) {
            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard')->with('login_success', $message);
            } else {
                return redirect()->route('subscription')->with('login_success', $message);
            }
        }

        return redirect()->route('user.profile')->with('login_success', $message);
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

    /**
     * Create tenant and bind password using Xiaoice API (same logic as VerificationController)
     */
    private function callXiaoiceApiWithCreds(User $user, string $plainPassword): array
    {
        if (!$this->validatePasswordFormat($plainPassword)) {
            Log::error('Password format validation failed before API call', [
                'user_id' => $user->id,
                'password_length' => strlen($plainPassword)
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => 'Password format is invalid. Please contact support.'
            ];
        }

        try {
            // Create the tenant
            $createResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/create',
                [
                    'name' => $user->name,
                    'regionCode' => 'OTHER',
                    'adminName' => $user->name,
                    'adminEmail' => $user->email,
                    'adminPhone' => '',
                    'adminPassword' => $plainPassword,
                    'appIds' => [1],
                ]
            );

            $createJson = $createResponse->json();
            // Handle case where tenant already exists (code 730)
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                Log::info('Tenant already exists for user (idempotent operation)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'api_code' => $createJson['code']
                ]);

                // Try to find if there's another user with same email that has tenant_id
                $existingUserWithTenant = User::where('email', $user->email)
                    ->whereNotNull('tenant_id')
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUserWithTenant) {
                    Log::info('Found existing user with tenant for same email, using existing tenant_id', [
                        'current_user_id' => $user->id,
                        'existing_user_id' => $existingUserWithTenant->id,
                        'tenant_id' => $existingUserWithTenant->tenant_id
                    ]);

                    // Use the existing tenant_id - this is idempotent
                    $tenantId = $existingUserWithTenant->tenant_id;
                } else {
                    // Tenant exists in external system but we don't have tenant_id in our DB
                    // Try to extract tenant_id from response if available
                    $tenantId = $createJson['data']['tenantId'] ?? null;

                    if (!$tenantId) {
                        Log::warning('Tenant exists but tenant_id not found in response or database', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'response' => $createJson
                        ]);

                        return [
                            'success' => false,
                            'data' => null,
                            'error_message' => 'This user is already registered. Please use a different email or contact support.',
                            'swal' => true
                        ];
                    }
                }

                // Continue with password binding using existing tenant_id
                Log::info('Proceeding with password binding for existing tenant', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
            } else {
                // Normal flow: tenant was created successfully
                if (!$createResponse->successful()) {
                    return $this->handleFailedTenantCreation($createResponse, $user);
                }

                Log::info('Tenant created successfully', [
                    'user_id' => $user->id,
                    'response' => $createResponse->json()
                ]);

                $tenantId = $createResponse->json()['data']['tenantId'] ?? null;
                if (!$tenantId) {
                    Log::error('Failed to extract tenantId from create response', [
                        'user_id' => $user->id,
                        'response' => $createResponse->json()
                    ]);
                    return [
                        'success' => false,
                        'data' => null,
                        'error_message' => 'Failed to create tenant. Missing tenantId in response.'
                    ];
                }
            }

            // Password binding - can be called multiple times safely
            $passwordBindResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/user/password/bind',
                [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]
            );

            $bindJson = $passwordBindResponse->json();

            // Handle various success scenarios
            // Some APIs return success codes even if already bound - treat as success
            $isSuccess = $passwordBindResponse->successful() &&
                        (isset($bindJson['code']) && in_array($bindJson['code'], [200, 201]));

            if (!$isSuccess) {
                // Check if error is due to password already being bound (idempotent case)
                $errorMessage = $bindJson['message'] ?? '';
                $errorCode = $bindJson['code'] ?? null;

                // If password is already bound with same value, treat as success (idempotent)
                if (str_contains(strtolower($errorMessage), 'already') ||
                    str_contains(strtolower($errorMessage), 'bound') ||
                    $errorCode == 200) {
                    Log::info('Password already bound (idempotent operation)', [
                        'user_id' => $user->id,
                        'response' => $bindJson
                    ]);
                    // Treat as success - password binding is idempotent
                } else {
                    // Real error occurred
                    if (!$passwordBindResponse->successful()) {
                        return $this->handleFailedPasswordBind($passwordBindResponse, $user);
                    }

                    $errorMessage = $this->translateXiaoiceError(
                        $errorCode,
                        $errorMessage ?: 'Password bind failed'
                    );

                    Log::error('Failed to bind password', [
                        'user_id' => $user->id,
                        'response' => $bindJson
                    ]);

                    return [
                        'success' => false,
                        'data' => null,
                        'error_message' => $errorMessage,
                        'swal' => true
                    ];
                }
            }

            Log::info('Password bound successfully (idempotent)', [
                'user_id' => $user->id,
                'response' => $bindJson
            ]);

            return [
                'success' => true,
                'data' => ['tenantId' => $tenantId],
                'error_message' => null
            ];

        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API', [
                'user_id' => $user->id,
                'exception_message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => 'System error: ' . $e->getMessage()
            ];
        }
    }

    private function makeXiaoiceApiRequest(string $endpoint, array $data): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim(config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp'), '/');
        $fullUrl = $baseUrl . '/' . ltrim($endpoint, '/');

        return Http::timeout(30)
            ->connectTimeout(15)
            ->retry(3, 1000)
            ->withHeaders([
                'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post($fullUrl, $data);
    }

    private function handleFailedTenantCreation(\Illuminate\Http\Client\Response $response, User $user): array
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

        Log::error('Xiaoice API call failed', [
            'user_id' => $user->id,
            'status' => $status,
            'error_message' => $errorMessage,
            'response_body' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage,
            'swal' => true
        ];
    }

    private function handleFailedPasswordBind(\Illuminate\Http\Client\Response $response, User $user): array
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

        Log::error('Failed to bind password', [
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

    private function translateXiaoiceError(?int $code, string $defaultMessage): string
    {
        return match ($code) {
            665 => 'The application is not activated for this tenant. Please contact support.',
            730 => 'This user is already registered. Please use a different email or contact support.',
            400 => 'Invalid request parameters.',
            401 => 'Authentication failed.',
            404 => 'Resource not found.',
            429 => 'Too many requests. Please try again later.',
            500 => 'Internal server error.',
            default => $defaultMessage
        };
    }

    private function validatePasswordFormat(string $password): bool
    {
        $pattern = '/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/';
        return preg_match($pattern, $password) === 1;
    }
}
