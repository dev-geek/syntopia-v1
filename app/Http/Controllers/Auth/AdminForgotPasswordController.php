<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;
use App\Notifications\AdminResetPasswordNotification;


class AdminForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.passwords.admin-email'); // Load the admin-specific reset page
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'city' => 'nullable|string',
            'pet' => 'nullable|string',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }
    
        // If the user is Super Admin (role = 1), ensure security questions are filled
        if ($user->role == 1) {
            if (empty($request->city) || empty($request->pet)) {
                return back()->withErrors([
                    'city' => 'You must fill in the security questions.',
                    'pet' => 'You must fill in the security questions.',
                ]);
            }
    
            if (
                strtolower($user->city) !== strtolower($request->city) || 
                strtolower($user->pet) !== strtolower($request->pet)
            ) {
                return back()->withErrors([
                    'city' => 'Security question answers are incorrect.',
                    'pet' => 'Security question answers are incorrect.',
                ]);
            }
        }
    
        // Generate Password Reset Token
        $token = Password::getRepository()->create($user);
    
        // Send custom notification with correct reset link
        $user->notify(new AdminResetPasswordNotification($token, $user->email));
    
        return back()->with(['status' => 'A password reset link has been sent to your email.']);
    }
        
    
}
