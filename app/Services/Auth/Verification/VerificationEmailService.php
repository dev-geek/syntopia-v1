<?php

namespace App\Services\Auth\Verification;

use App\Models\User;
use App\Mail\VerifyEmail;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class VerificationEmailService
{
    public function resendVerificationCode(User $user): array
    {
        $user->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->verification_code_sent_at = now();
        $user->save();

        $mailResult = MailService::send($user->email, new VerifyEmail($user));

        if ($mailResult['success']) {
            return [
                'success' => true,
                'message' => 'Verification code has been resent'
            ];
        }

        Log::error('Failed to resend verification email', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $mailResult['error'] ?? 'Unknown error'
        ]);

        session(['verification_code' => $user->verification_code]);
        return [
            'success' => false,
            'error' => $mailResult['message'] ?? 'Failed to send verification email'
        ];
    }
}
