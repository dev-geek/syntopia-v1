<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class VerificationTestController extends Controller
{
    public function verifyCode(Request $request)
    {
        \Log::info('Verification attempt', $request->all());

        $request->validate([
            'verification_code' => 'required'
        ]);

        $user = User::where('verification_code', $request->verification_code)->first();

        if ($user) {
            // Get the intended URL from session
            $intendedUrl = session()->get('url.intended', route('profile')); // fallback to profile if no intended URL
            
            // Clear the verification code as it's no longer needed
            $user->verification_code = null;
            $user->email_verified_at = now();
            $user->save();

            // Clear the intended URL from session
            session()->forget('url.intended');

            // Redirect to the intended URL
            return redirect($intendedUrl);
        }

        return back()->withErrors(['verification_code' => 'Invalid verification code']);
    }
}
