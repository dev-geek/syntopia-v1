<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;

class SoftwareAccessController extends Controller
{
    /**
     * Generate a secure access token for software login
     */
    public function generateAccessToken()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Check if user has access to software
        if ($user->hasRole('Super Admin') || $user->hasRole('Sub Admin')) {
            return response()->json(['error' => 'Admins cannot access software directly'], 403);
        }

        // Create a temporary token with encrypted credentials
        $tokenData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'password' => $user->subscriber_password, // Use the plain text password for API
            'expires_at' => Carbon::now()->addMinutes(5)->timestamp, // Token expires in 5 minutes
            'nonce' => Str::random(16)
        ];

        // Encrypt the token data
        $encryptedToken = Crypt::encryptString(json_encode($tokenData));

        // Create the software URL with the token
        $softwareUrl = 'https://live.syntopia.ai/login?token=' . urlencode($encryptedToken);

        return response()->json([
            'success' => true,
            'software_url' => $softwareUrl
        ]);
    }

    /**
     * Redirect to software with pre-filled credentials
     */
    public function redirectToSoftware()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login first');
        }

        // Check if user has access to software
        if ($user->hasRole('Super Admin') || $user->hasRole('Sub Admin')) {
            return redirect()->back()->with('error', 'Admins cannot access software directly');
        }

        // Check if user has valid subscriber password
        if (!$user->hasValidSubscriberPassword()) {
            return redirect()->back()->with('error', 'Your account is not properly configured for software access');
        }

        // Generate access token and redirect
        $tokenData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'password' => $user->subscriber_password,
            'expires_at' => Carbon::now()->addMinutes(5)->timestamp,
            'nonce' => Str::random(16)
        ];

        $encryptedToken = Crypt::encryptString(json_encode($tokenData));
        $softwareUrl = 'https://live.syntopia.ai/login?token=' . urlencode($encryptedToken);

        return redirect($softwareUrl);
    }
}
