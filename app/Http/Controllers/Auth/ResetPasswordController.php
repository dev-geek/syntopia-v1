<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResetPasswordController extends Controller
{
    use ResetsPasswords;

    public function __construct(
        private PasswordResetService $passwordResetService
    ) {}

    protected function redirectTo()
    {
        $user = Auth::user();

        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return route('admin.dashboard');
        }

        return '/dashboard';
    }

    public function showResetForm(Request $request, $token = null)
    {
        $email = $request->email;
        $isAdmin = false;

        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();
            $isAdmin = $user && $user->hasAnyRole(['Super Admin', 'Sub Admin']);
        }

        $view = $isAdmin ? 'auth.passwords.admin-reset' : 'auth.passwords.reset';

        return view($view)->with([
            'token' => $token,
            'email' => $email
        ]);
    }

    public function reset(ResetPasswordRequest $request)
    {
        try {
            $result = $this->passwordResetService->resetPassword($request, $request->validated());

            if (!$result['success']) {
                return back()->withErrors([$result['error'] => $result['message']])->withInput();
            }

            auth()->login($result['user']);

            $redirectTo = $this->redirectTo();
            $messageKey = $result['is_admin'] ? 'status' : 'success';

            return redirect($redirectTo)->with($messageKey, $result['message']);

        } catch (\Exception $e) {
            Log::error('Unexpected error during password reset', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['email' => 'An unexpected error occurred. Please try again or contact support.'])->withInput();
        }
    }
}
