<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use App\Services\MailService;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ], [
                'email.required' => 'Email address is required.',
                'email.email' => 'Please enter a valid email address.',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Check if user exists
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                Log::info('Password reset requested for non-existent email', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                // Don't reveal if email exists or not for security
                return back()->with('status', 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.');
            }

            // Send the reset link
            $response = $this->broker()->sendResetLink(
                $request->only('email')
            );

            if ($response === Password::RESET_LINK_SENT) {
                Log::info('Password reset link sent successfully', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                return back()->with('status', 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.');
            } else {
                Log::warning('Failed to send password reset link', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'response' => $response
                ]);

                // Check if it's a mail service error
                if (str_contains($response, 'mail') || str_contains($response, 'connection')) {
                    return back()->withErrors(['email' => 'Email service is temporarily unavailable. Please try again later or contact support.'])->withInput();
                }

                return back()->withErrors(['email' => 'Unable to send password reset link. Please try again later.'])->withInput();
            }

        } catch (\Exception $e) {
            Log::error('Error sending password reset link', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            return back()->withErrors(['email' => 'An unexpected error occurred. Please try again later.'])->withInput();
        }
    }
}
