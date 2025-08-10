<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.passwords.admin-email');
    }

    public function checkEmail(Request $request)
    {
        \Log::info('AdminForgotPasswordController@checkEmail - Request received', [
            'email' => $request->email,
            'all' => $request->all()
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                \Log::warning('AdminForgotPasswordController@checkEmail - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'error' => 'The provided email is invalid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                \Log::warning('AdminForgotPasswordController@checkEmail - User not found', [
                    'email' => $request->email
                ]);
                return response()->json([
                    'error' => 'No account found with this email address.'
                ], 404);
            }

            $response = [
                'requires_security_questions' => $user->role == 1,
                'redirect_url' => $user->role == 1 
                    ? route('admin.password.request', ['email' => $user->email, 'show_questions' => true])
                    : null
            ];

            \Log::info('AdminForgotPasswordController@checkEmail - Success', [
                'user_id' => $user->id,
                'role' => $user->role,
                'response' => $response
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('AdminForgotPasswordController@checkEmail - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'An unexpected error occurred. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'city' => 'nullable|string|max:255',
            'pet' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found with this email address.']);
        }

        // If it's a super admin, validate security questions
        if ($user->role == 1) {
            $securityValidator = Validator::make($request->only(['city', 'pet']), [
                'city' => 'required|string|max:255',
                'pet' => 'required|string|max:255',
            ]);

            if ($securityValidator->fails()) {
                return back()
                    ->withErrors($securityValidator)
                    ->withInput($request->only('email', 'city', 'pet'));
            }

            // Verify security answers (case insensitive)
            if (strtolower($user->city) !== strtolower($request->city) || 
                strtolower($user->pet) !== strtolower($request->pet)) {
                
                return back()
                    ->withInput($request->only('email', 'city', 'pet'))
                    ->withErrors([
                        'security' => 'The security answers you provided are incorrect. Please try again.'
                    ]);
            }
        }

        try {
            // Generate and send password reset token
            $token = Password::getRepository()->create($user);
            $user->notify(new AdminResetPasswordNotification($token, $user->email));

            return back()->with('status', [
                'type' => 'success',
                'message' => 'A password reset link has been sent to your email address.'
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'An error occurred while processing your request. Please try again.'
                ]);
        }
    }
}
