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
                return redirect()->route('profile')->with('login_success', 'User Login Successfully');
                
            } else {
                // Check if a user with the same email exists
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Associate the Google ID with the existing user
                    $existingUser->update(['google_id' => $googleUser->id]);
                    Auth::login($existingUser);
                    return redirect()->route('/');
                } else {
                    // Create a new user
                    $userData = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'password' => Hash::make('12345678'), // Default password for the new user
                        'email_verified_at' => Carbon::now(), // Set email verification timestamp with Carbon

                        
                    ]);
                   
                    if ($userData) {
                        Auth::login($userData);
                        return redirect()->route('/');
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
            $user = Socialite::driver('facebook')->user();
            $finduser = User::where('facebook_id', $user->id)->first();

            if ($finduser) {
                Auth::login($finduser);
                return redirect()->intended('home');
            } else {
                $newUser = User::create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'facebook_id'=> $user->id,
                    'password' => encrypt('123456dummy'),
                    'email_verified_at' => now() // Set email verification timestamp

                ]);

                Auth::login($newUser);
                return redirect()->intended('home');
            }
        } catch (\Exception $e) {
            // dd($e->getMessage());
            return redirect()->intended('home');
        }

    }
}
