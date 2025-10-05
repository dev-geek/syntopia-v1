<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Check if user has valid subscriber password
        if (!$user->hasValidSubscriberPassword()) {
            return redirect()->back()->with('error', 'Your account is not properly configured for software access');
        }

        // Redirect to software without token
        $softwareUrl = 'https://live.syntopia.ai/login';

        return redirect($softwareUrl);
    }
}
