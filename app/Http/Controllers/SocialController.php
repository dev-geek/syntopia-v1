<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class SocialController extends Controller
{
    public function googleLogin()
    {
        return Socialite::driver('google')->redirect();
    }
    public function googleAuthentication()
    {
        try {
            // Get the user information from Google
            $googleUser = Socialite::driver('google')->stateless()->user();

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
                    // Associate the Google ID with the existing user
                    $existingUser->update([
                        'google_id' => $googleUser->id,
                        'email_verified_at' => Carbon::now(),
                        'status' => 1 // Ensure user is activated
                    ]);
                    Auth::login($existingUser);
                    if ($existingUser->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                        return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                    }

                    // For regular users, check subscription status
                    if ($existingUser->hasRole('User')) {
                        // Check for intended URL first
                        if (session()->has('url.intended')) {
                            $intendedUrl = session('url.intended');
                            session()->forget('url.intended');
                            return redirect()->to($intendedUrl)->with('login_success', 'Account linked with Google successfully');
                        }

                        if ($this->hasActiveSubscription($existingUser)) {
                            return redirect()->route('user.dashboard')->with('login_success', 'Account linked with Google successfully');
                        } else {
                            return redirect()->route('home')->with('login_success', 'Account linked with Google successfully');
                        }
                    }

                    return redirect()->route('user.profile')->with('login_success', 'Account linked with Google successfully');
                } else {
                    // Create a new user
                    $userData = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'password' => Hash::make('12345678'), // Default password for the new user
                        'email_verified_at' => Carbon::now(),
                        'status' => 1,
                        'verification_code' => null
                    ]);

                    $userData->assignRole('User');

                    if ($userData) {
                        Auth::login($userData);

                        // Check for intended URL first
                        if (session()->has('url.intended')) {
                            $intendedUrl = session('url.intended');
                            session()->forget('url.intended');
                            return redirect()->to($intendedUrl)->with('login_success', 'Welcome! Account created successfully with Google');
                        }

                        // New users should go to home since they don't have a subscription yet
                        return redirect()->route('home')->with('login_success', 'Welcome! Account created successfully with Google');
                    }
                }
            }
        } catch (\Exception $e) {
            // Log the error and redirect to login with an error message
            logger()->error('Google Authentication Error: ' . $e->getMessage());
            return redirect()->route('login')->withErrors('Failed to authenticate with Google. Please try again.');
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();

            // Check if user with Facebook ID exists
            $user = User::where('facebook_id', $facebookUser->id)->first();

            if ($user) {
                Auth::login($user);

                // Redirect based on role
                if ($user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                    return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                }

                // For regular users, check subscription status
                if ($user->hasRole('User')) {
                    // Check for intended URL first
                    if (session()->has('url.intended')) {
                        $intendedUrl = session('url.intended');
                        session()->forget('url.intended');
                        return redirect()->to($intendedUrl)->with('login_success', 'User Login Successfully');
                    }

                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')->with('login_success', 'User Login Successfully');
                    } else {
                        return redirect()->route('home')->with('login_success', 'User Login Successfully');
                    }
                }

                return redirect()->route('user.profile')->with('login_success', 'User Login Successfully');
            } else {
                // Check if user with same email exists
                $existingUser = User::where('email', $facebookUser->email)->first();

                if ($existingUser) {
                    // Link Facebook account to existing user
                    $existingUser->update([
                        'facebook_id' => $facebookUser->id,
                        'email_verified_at' => Carbon::now(),
                        'status' => 1
                    ]);

                    Auth::login($existingUser);

                    // Redirect based on role
                    if ($existingUser->hasAnyRole(['Sub Admin', 'Super Admin'])) {
                        return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                    }

                    // For regular users, check subscription status
                    if ($existingUser->hasRole('User')) {
                        // Check for intended URL first
                        if (session()->has('url.intended')) {
                            $intendedUrl = session('url.intended');
                            session()->forget('url.intended');
                            return redirect()->to($intendedUrl)->with('login_success', 'Account linked with Facebook successfully');
                        }

                        if ($this->hasActiveSubscription($existingUser)) {
                            return redirect()->route('user.dashboard')->with('login_success', 'Account linked with Facebook successfully');
                        } else {
                            return redirect()->route('home')->with('login_success', 'Account linked with Facebook successfully');
                        }
                    }

                    return redirect()->route('user.profile')->with('login_success', 'Account linked with Facebook successfully');
                } else {
                    // Create new user
                    $newUser = User::create([
                        'name' => $facebookUser->name,
                        'email' => $facebookUser->email,
                        'facebook_id' => $facebookUser->id,
                        'password' => Hash::make('12345678'),
                        'email_verified_at' => Carbon::now(),
                        'status' => 1,
                        'verification_code' => null
                    ]);

                    // Assign default role
                    $newUser->assignRole('User');

                    Auth::login($newUser);

                    // Check for intended URL first
                    if (session()->has('url.intended')) {
                        $intendedUrl = session('url.intended');
                        session()->forget('url.intended');
                        return redirect()->to($intendedUrl)->with('login_success', 'Welcome! Account created successfully with Facebook');
                    }

                    return redirect()->route('profile')->with('login_success', 'Welcome! Account created successfully with Facebook');
                }
            }
        } catch (\Exception $e) {
            // dd($e->getMessage());
            return redirect()->route('login')->withErrors('Failed to authenticate with Facebook. Please try again.');
        }

    }

    /**
     * Check if user has an active subscription
     */
    private function hasActiveSubscription($user)
    {
        if (!$user->is_subscribed || !$user->subscription_starts_at || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        $startDate = \Carbon\Carbon::parse($user->subscription_starts_at);
        $durationInDays = $user->package->getDurationInDays();
        $endDate = $durationInDays ? $startDate->copy()->addDays($durationInDays) : null;

        return $endDate ? \Carbon\Carbon::now()->lte($endDate) : $user->is_subscribed;
    }
}
