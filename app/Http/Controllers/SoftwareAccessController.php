<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

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
        if ($user->hasRole('Super Admin')) {
            return response()->json(['error' => 'Admins cannot access software directly'], 403);
        }

        // Create the software URL without token
        $softwareUrl = 'https://live.syntopia.ai/login';

        return response()->json([
            'success' => true,
            'software_url' => $softwareUrl
        ]);
    }

    /**
     * Redirect to software without token
     */
    public function redirectToSoftware()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login first');
        }

        // Check if user has access to software
        if ($user->hasRole('Super Admin')) {
            return redirect()->back()->with('error', 'Admins cannot access software directly');
        }

        // Check if user has valid subscriber password.
        // If not, but they DO have tenant_id and an active subscription, allow access (legacy users with licenses).
        if (!$user->hasValidSubscriberPassword()) {
            if (!$user->tenant_id || !$user->hasActiveSubscription()) {
                return redirect()->back()->with('error', 'Your account is not properly configured for software access');
            }

            Log::warning('Allowing software access without valid subscriber_password because user has active subscription', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
            ]);
        }

        // Redirect to software without token
        $softwareUrl = 'https://live.syntopia.ai/login';

        return redirect($softwareUrl);
    }
}
