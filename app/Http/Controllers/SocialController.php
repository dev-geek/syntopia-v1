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
                if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                    return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                }

                // For regular users, check subscription status
                if ($user->hasRole('User')) {
                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')->with('login_success', 'User Login Successfully');
                    } else {
                        return redirect()->route('home')->with('login_success', 'User Login Successfully');
                    }
                }

                return redirect()->route('user.profile')->with('login_success', 'User Login Successfully');

            } else {
                // Check if a user with the same email exists
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Try to link Google account with existing user
                    try {
                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the existing user
                        $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for existing user during Google link, proceeding with fallback', [
                                'user_id' => $existingUser->id,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Link account without updating password
                            $existingUser->update([
                                'google_id' => $googleUser->id,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1
                            ]);

                            Auth::login($existingUser);

                            return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Note: You may need to update your password later for full functionality.');
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Google successfully!');

                    } catch (\Exception $e) {
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
                    // Create new user
                    try {
                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the new user
                        $apiResponse = $passwordBindingService->bindPassword(
                            (new User())->forceFill(['email' => $googleUser->email]),
                            $compliantPassword
                        );

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for new Google user, proceeding with fallback', [
                                'email' => $googleUser->email,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Create user with temporary password
                            $tempPassword = $this->generateCompliantPassword();
                            $userData = User::create([
                                'name' => $googleUser->name,
                                'email' => $googleUser->email,
                                'google_id' => $googleUser->id,
                                'password' => Hash::make($tempPassword),
                                'subscriber_password' => $tempPassword,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1,
                                'verification_code' => null
                            ]);

                            $userData->assignRole('User');
                            Auth::login($userData);

                            return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Google. Please update your password in your profile for full functionality.');
                        }

                        // Success: Create user with proper password
                        $userData = User::create([
                            'name' => $googleUser->name,
                            'email' => $googleUser->email,
                            'google_id' => $googleUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $userData->assignRole('User');
                        Auth::login($userData);

                        return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Google');

                    } catch (\Exception $e) {
                        Log::error('Error creating new Google user', [
                            'email' => $googleUser->email,
                            'error' => $e->getMessage()
                        ]);

                        // Final fallback: Create user with basic info
                        $tempPassword = $this->generateCompliantPassword();
                        $userData = User::create([
                            'name' => $googleUser->name,
                            'email' => $googleUser->email,
                            'google_id' => $googleUser->id,
                            'password' => Hash::make($tempPassword),
                            'subscriber_password' => $tempPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $userData->assignRole('User');
                        Auth::login($userData);

                        return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Google. Please update your password in your profile for full functionality.');
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
                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the existing user
                        $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for existing user during Facebook link, proceeding with fallback', [
                                'user_id' => $existingUser->id,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Link account without updating password
                            $existingUser->update([
                                'facebook_id' => $facebookUser->id,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1
                            ]);

                            Auth::login($existingUser);
                            return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Note: You may need to update your password later for full functionality.');
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Facebook successfully!');

                    } catch (\Exception $e) {
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
                    // Create new user
                    try {
                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the new user
                        $apiResponse = $passwordBindingService->bindPassword(
                            (new User())->forceFill(['email' => $facebookUser->email]),
                            $compliantPassword
                        );

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for new Facebook user, proceeding with fallback', [
                                'email' => $facebookUser->email,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Create user with temporary password
                            $tempPassword = $this->generateCompliantPassword();
                            $newUser = User::create([
                                'name' => $facebookUser->name,
                                'email' => $facebookUser->email,
                                'facebook_id' => $facebookUser->id,
                                'password' => Hash::make($tempPassword),
                                'subscriber_password' => $tempPassword,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1,
                                'verification_code' => null
                            ]);

                            $newUser->assignRole('User');
                            Auth::login($newUser);

                            return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook. Please update your password in your profile for full functionality.');
                        }

                        // Success: Create user with proper password
                        $newUser = User::create([
                            'name' => $facebookUser->name,
                            'email' => $facebookUser->email,
                            'facebook_id' => $facebookUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $newUser->assignRole('User');
                        Auth::login($newUser);

                        return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook');

                    } catch (\Exception $e) {
                        Log::error('Error creating new Facebook user', [
                            'email' => $facebookUser->email,
                            'error' => $e->getMessage()
                        ]);

                        // Final fallback: Create user with basic info
                        $tempPassword = $this->generateCompliantPassword();
                        $newUser = User::create([
                            'name' => $facebookUser->name,
                            'email' => $facebookUser->email,
                            'facebook_id' => $facebookUser->id,
                            'password' => Hash::make($tempPassword),
                            'subscriber_password' => $tempPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $newUser->assignRole('User');
                        Auth::login($newUser);

                        return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook. Please update your password in your profile for full functionality.');
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

        if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return redirect()->route('admin.dashboard')->with('login_success', $message);
        }

        if ($user->hasRole('User')) {
            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard')->with('login_success', $message);
            } else {
                return redirect()->route('home')->with('login_success', $message);
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
