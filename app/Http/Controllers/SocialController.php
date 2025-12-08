<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Services\PasswordBindingService;
use App\Services\TenantAssignmentService;
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

    public function googleAuthentication(PasswordBindingService $passwordBindingService, TenantAssignmentService $tenantAssignmentService)
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
                            $apiResponse = $tenantAssignmentService->assignTenant($existingUser, $compliantPassword);

                            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                Log::error('[googleAuthentication] API returned swal error during tenant creation for existing user - will retry later', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);
                                // Continue with account linking even if tenant assignment failed - will retry later
                            } elseif (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                                Log::error('[googleAuthentication] Failed to create tenant_id for existing user - will retry later', [
                                    'user_id' => $existingUser->id,
                                    'apiResponse' => $apiResponse
                                ]);
                                // Continue with account linking even if tenant assignment failed - will retry later
                            } else {
                                // Update user with tenant_id only if assignment was successful
                                $existingUser->update([
                                    'tenant_id' => $apiResponse['data']['tenantId'],
                                ]);
                            }

                            // Update user with tenant_id
                            $existingUser->update([
                                'tenant_id' => $apiResponse['data']['tenantId'],
                            ]);
                        } else {
                            // User already has tenant_id, just bind password
                            $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                            if (!$apiResponse['success']) {
                                Log::warning('Failed to bind password for existing user during Google link - will retry later', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);
                                // Continue with account linking even if password binding failed - will retry later
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
                        Log::error('Error during Google account linking - continuing with account link', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Continue with account linking even if there was an error - will retry later
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        DB::commit();
                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Some operations may be retried automatically.');
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

                        // Create tenant and bind password using TenantAssignmentService
                        Log::info('[googleAuthentication] Calling TenantAssignmentService for new Google user', ['user_id' => $userData->id]);
                        $apiResponse = $tenantAssignmentService->assignTenant($userData, $compliantPassword);
                        Log::info('[googleAuthentication] TenantAssignmentService response', ['user_id' => $userData->id, 'apiResponse' => $apiResponse]);

                        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                            Log::error('[googleAuthentication] API returned swal error - will retry tenant assignment later', ['user_id' => $userData->id, 'error' => $apiResponse['error_message']]);
                            // Continue with user creation even if tenant assignment failed - will retry later
                        } elseif (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                            Log::error('[googleAuthentication] API failed or missing tenantId - will retry tenant assignment later', [
                                'user_id' => $userData->id,
                                'apiResponse' => $apiResponse
                            ]);
                            // Continue with user creation even if tenant assignment failed - will retry later
                        } else {
                            // Update user with tenant_id only if assignment was successful
                            $userData->update([
                                'tenant_id' => $apiResponse['data']['tenantId'],
                            ]);
                        }

                        // Always update subscriber_password
                        $userData->update([
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

    public function handleFacebookCallback(PasswordBindingService $passwordBindingService, TenantAssignmentService $tenantAssignmentService)
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
                            $apiResponse = $tenantAssignmentService->assignTenant($existingUser, $compliantPassword);

                            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                Log::error('[handleFacebookCallback] API returned swal error during tenant creation for existing user - will retry later', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);
                                // Continue with account linking even if tenant assignment failed - will retry later
                            } elseif (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                                Log::error('[handleFacebookCallback] Failed to create tenant_id for existing user - will retry later', [
                                    'user_id' => $existingUser->id,
                                    'apiResponse' => $apiResponse
                                ]);
                                // Continue with account linking even if tenant assignment failed - will retry later
                            } else {
                                // Update user with tenant_id only if assignment was successful
                                $existingUser->update([
                                    'tenant_id' => $apiResponse['data']['tenantId'],
                                ]);
                            }
                        } else {
                            // User already has tenant_id, just bind password
                            $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                            if (!$apiResponse['success']) {
                                Log::warning('Failed to bind password for existing user during Facebook link, proceeding with fallback', [
                                    'user_id' => $existingUser->id,
                                    'error' => $apiResponse['error_message']
                                ]);

                                // Continue with account linking even if password binding failed - will retry later
                                $existingUser->update([
                                    'facebook_id' => $facebookUser->id,
                                    'email_verified_at' => Carbon::now(),
                                    'status' => 1,
                                    'password' => Hash::make($compliantPassword),
                                    'subscriber_password' => $compliantPassword
                                ]);

                                DB::commit();
                                Auth::login($existingUser);
                                return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Some operations may be retried automatically.');
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
                        Log::error('Error during Facebook account linking - continuing with account link', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Continue with account linking even if there was an error - will retry later
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        DB::commit();
                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Some operations may be retried automatically.');
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

                        // Create tenant and bind password using TenantAssignmentService
                        Log::info('[handleFacebookCallback] Calling TenantAssignmentService for new Facebook user', ['user_id' => $userData->id]);
                        $apiResponse = $tenantAssignmentService->assignTenant($userData, $compliantPassword);
                        Log::info('[handleFacebookCallback] TenantAssignmentService response', ['user_id' => $userData->id, 'apiResponse' => $apiResponse]);

                        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                            Log::error('[handleFacebookCallback] API returned swal error - will retry tenant assignment later', ['user_id' => $userData->id, 'error' => $apiResponse['error_message']]);
                            // Continue with user creation even if tenant assignment failed - will retry later
                        } elseif (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                            Log::error('[handleFacebookCallback] API failed or missing tenantId - will retry tenant assignment later', [
                                'user_id' => $userData->id,
                                'apiResponse' => $apiResponse
                            ]);
                            // Continue with user creation even if tenant assignment failed - will retry later
                        } else {
                            // Update user with tenant_id only if assignment was successful
                            $userData->update([
                                'tenant_id' => $apiResponse['data']['tenantId'],
                            ]);
                        }

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
                        Log::error('[handleFacebookCallback] Exception during new Facebook user creation - keeping user for retry', [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email' => $facebookUser->email,
                            'user_id' => $userData->id ?? null
                        ]);

                        // Commit the transaction to save the user even if there was an error
                        // This allows retry mechanisms to work later
                        if (isset($userData) && DB::transactionLevel() > 0) {
                            DB::commit();
                        }

                        // Don't delete user - allow retry mechanisms to handle it
                        if (isset($userData)) {
                            return redirect()->route('login')->with('error', 'Account created but some operations failed. Please try logging in - operations will be retried automatically.');
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
}
