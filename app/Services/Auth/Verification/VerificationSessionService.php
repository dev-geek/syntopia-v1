<?php

namespace App\Services\Auth\Verification;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerificationSessionService
{
    public function getEmailFromSessionOrRequest(Request $request): ?string
    {
        $email = session('email');

        if (!$email && $request->has('email')) {
            $email = $request->input('email');
            session(['email' => $email]);
            }

        if (!$email) {
            Log::error('[VerificationSessionService] No email in session or request');
        }

        return $email;
    }

    public function getUserByEmail(string $email): ?User
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::error('[VerificationSessionService] User not found', ['email' => $email]);
        }

        return $user;
    }

    public function clearEmailSession(): void
    {
        session()->forget('email');
    }

    public function isUserAlreadyVerified(User $user): bool
    {
        return $user->status == 1 && !is_null($user->email_verified_at);
    }
}
