<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
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
     * @param  \App\Http\Requests\ResetPasswordRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset(ResetPasswordRequest $request, PasswordBindingService $passwordBindingService)
    {
        try {

            // Get validated data
            $validated = $request->validated();

            // Find the user by email
            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                Log::warning('Password reset attempted for non-existent email', [
                    'email' => $validated['email'],
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
            }

            // Verify the password reset token
            $tokenData = DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->first();

            if (!$tokenData || !Hash::check($validated['token'], $tokenData->token)) {
                Log::warning('Invalid password reset token used', [
                    'email' => $validated['email'],
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'Invalid or expired reset token. Please request a new password reset.'])->withInput();
            }

            // Check if token is expired (24 hours)
            if (now()->diffInHours($tokenData->created_at) > 24) {
                Log::warning('Expired password reset token used', [
                    'email' => $validated['email'],
                    'ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'Reset token has expired. Please request a new password reset.'])->withInput();
            }

            // Call password binding API before updating the database
            $apiResponse = $passwordBindingService->bindPassword($user, $validated['password']);

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
                $user->password = Hash::make($validated['password']);
                $user->subscriber_password = $validated['password'];
                $user->save();

                // Delete the used reset token
                DB::table('password_reset_tokens')
                    ->where('email', $validated['email'])
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
