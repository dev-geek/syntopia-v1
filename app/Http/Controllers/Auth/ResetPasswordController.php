<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordBindingService;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Exception;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Display the password reset view for the given token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset(Request $request, PasswordBindingService $passwordBindingService)
    {
        try {
            // Validate the request
            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:30',
                    'confirmed',
                    'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'
                ],
            ], [
                'token.required' => 'Reset token is required.',
                'email.required' => 'Email address is required.',
                'email.email' => 'Please enter a valid email address.',
                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters long.',
                'password.max' => 'Password cannot exceed 30 characters.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (,.<>{}~!@#$%^&_).'
            ]);

            // Find the user by email
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                Log::warning('Password reset attempted for non-existent email', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
            }

            // Verify the password reset token
            $tokenData = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$tokenData || !Hash::check($request->token, $tokenData->token)) {
                Log::warning('Invalid password reset token used', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'Invalid or expired reset token. Please request a new password reset.'])->withInput();
            }

            // Check if token is expired (24 hours)
            if (now()->diffInHours($tokenData->created_at) > 24) {
                Log::warning('Expired password reset token used', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'Reset token has expired. Please request a new password reset.'])->withInput();
            }

            // Call password binding API before updating the database
            $apiResponse = $passwordBindingService->bindPassword($user, $request->password);

            if (!$apiResponse['success']) {
                Log::error('Password binding API failed during reset', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $apiResponse['error_message']
                ]);
                return back()->withErrors(['password' => 'Unable to update password in external system. Please try again or contact support.'])->withInput();
            }

            // Update password in database within a transaction
            DB::beginTransaction();
            try {
                $user->password = Hash::make($request->password);
                $user->subscriber_password = $request->password;
                $user->save();

                // Delete the used reset token
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();

                DB::commit();

                Log::info('Password reset successful', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);

                // Log the user in after successful password reset
                auth()->login($user);

                return redirect($this->redirectTo)->with('success', 'Password successfully updated and synchronized with external services.');

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Database error during password reset', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
                return back()->withErrors(['password' => 'Unable to save password. Please try again.'])->withInput();
            }

        } catch (Exception $e) {
            Log::error('Unexpected error during password reset', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['email' => 'An unexpected error occurred. Please try again or contact support.'])->withInput();
        }
    }
}
