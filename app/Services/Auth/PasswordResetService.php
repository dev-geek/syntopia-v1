<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use App\Services\PasswordBindingService;
use App\Notifications\AdminResetPasswordNotification;

class PasswordResetService
{
    public function __construct(
        private PasswordBindingService $passwordBindingService
    ) {}

    public function checkEmail(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'No account found with this email address.'
            ];
        }

        $isSuperAdmin = $user->hasRole('Super Admin');

        return [
            'success' => true,
            'requires_security_questions' => $isSuperAdmin,
            'redirect_url' => $isSuperAdmin
                ? route('admin.password.request', ['email' => $user->email, 'show_questions' => true])
                : null
        ];
    }

    public function validateSecurityQuestions(User $user, Request $request): array
    {
        if (!$user->hasRole('Super Admin')) {
            return ['success' => true];
        }

        if (strtolower($user->city) !== strtolower($request->city) ||
            strtolower($user->pet) !== strtolower($request->pet)) {
            return [
                'success' => false,
                'error' => 'security',
                'message' => 'The security answers you provided are incorrect. Please try again.'
            ];
        }

        return ['success' => true];
    }

    public function sendResetLink(Request $request): array
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::info('Password reset requested for non-existent email', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            return [
                'success' => true,
                'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.'
            ];
        }

        $response = Password::broker()->sendResetLink($request->only('email'));

        if ($response === Password::RESET_LINK_SENT) {
            Log::info('Password reset link sent successfully', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            return [
                'success' => true,
                'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.'
            ];
        }

        Log::warning('Failed to send password reset link', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'response' => $response
        ]);

        if (str_contains($response, 'mail') || str_contains($response, 'connection')) {
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'Email service is temporarily unavailable. Please try again later or contact support.'
            ];
        }

        return [
            'success' => false,
            'error' => 'email',
            'message' => 'Unable to send password reset link. Please try again later.'
        ];
    }

    public function sendAdminResetLink(User $user): array
    {
        try {
            $token = Password::getRepository()->create($user);
            $user->notify(new AdminResetPasswordNotification($token, $user->email));

            return [
                'success' => true,
                'message' => 'A password reset link has been sent to your email address.'
            ];
        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'An error occurred while processing your request. Please try again.'
            ];
        }
    }

    public function resetPassword(Request $request, array $validated): array
    {
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            Log::warning('Password reset attempted for non-existent email', [
                'email' => $validated['email'],
                'ip' => $request->ip()
            ]);
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'No account found with this email address.'
            ];
        }

        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$tokenData || !Hash::check($validated['token'], $tokenData->token)) {
            Log::warning('Invalid password reset token used', [
                'email' => $validated['email'],
                'ip' => $request->ip()
            ]);
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'Invalid or expired reset token. Please request a new password reset.'
            ];
        }

        if (now()->diffInHours($tokenData->created_at) > 24) {
            Log::warning('Expired password reset token used', [
                'email' => $validated['email'],
                'ip' => $request->ip()
            ]);
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'Reset token has expired. Please request a new password reset.'
            ];
        }

        $isAdmin = $user->hasAnyRole(['Super Admin', 'Sub Admin']);
        $apiResponse = null;
        $passwordBindingSuccess = false;

        if (!$isAdmin && $user->hasRole('User')) {
            $apiResponse = $this->passwordBindingService->bindPasswordWithRetry($user, $validated['password']);

            if (!$apiResponse['success']) {
                Log::warning('Password binding API failed during reset - will retry later', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $apiResponse['error_message']
                ]);
            } else {
                $passwordBindingSuccess = true;
            }
        }

        $user->password = $validated['password'];

        if (!$isAdmin && $user->hasRole('User')) {
            $user->subscriber_password = $validated['password'];
        }

        $user->save();

        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'is_admin' => $isAdmin,
            'password_binding_success' => $passwordBindingSuccess
        ]);

        if ($isAdmin) {
            $message = 'Password successfully updated.';
        } else {
            $message = $passwordBindingSuccess
                ? 'Password successfully updated and synchronized with external services.'
                : 'Password updated successfully. Password binding will be retried automatically.';
        }

        return [
            'success' => true,
            'user' => $user,
            'message' => $message,
            'is_admin' => $isAdmin
        ];
    }
}
