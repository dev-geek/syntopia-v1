<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\PasswordResetService;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function __construct(
        private PasswordResetService $passwordResetService
    ) {}

    public function showLinkRequestForm(Request $request)
    {
        $isAdminRoute = $request->is('admin/forgotpassword') || $request->is('admin/*');

        if ($isAdminRoute) {
            return view('auth.passwords.admin-email');
        }

        return view('auth.passwords.email');
    }

    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $result = $this->passwordResetService->checkEmail($request->email);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['error']
                ], 404);
            }

            return response()->json([
                'requires_security_questions' => $result['requires_security_questions'],
                'redirect_url' => $result['redirect_url']
            ]);

        } catch (\Exception $e) {
            Log::error('ForgotPasswordController@checkEmail - Error', [
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
        $isAdminRoute = $request->is('admin/password/email') || $request->is('admin/*');

        if ($isAdminRoute) {
            return $this->sendAdminResetLink($request);
        }

        return $this->sendUserResetLink($request);
    }

    private function sendUserResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $result = $this->passwordResetService->sendResetLink($request);

            if ($result['success']) {
                return back()->with('status', $result['message']);
            }

            return back()->withErrors([$result['error'] => $result['message']])->withInput();

        } catch (\Exception $e) {
            Log::error('Error sending password reset link', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            return back()->withErrors(['email' => 'An unexpected error occurred. Please try again later.'])->withInput();
        }
    }

    private function sendAdminResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'city' => 'nullable|string|max:255',
            'pet' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found with this email address.']);
        }

        $securityResult = $this->passwordResetService->validateSecurityQuestions($user, $request);

        if (!$securityResult['success']) {
            return back()
                ->withInput($request->only('email', 'city', 'pet'))
                ->withErrors([$securityResult['error'] => $securityResult['message']]);
        }

        $result = $this->passwordResetService->sendAdminResetLink($user);

        if (!$result['success']) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([$result['error'] => $result['message']]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => $result['message']
        ]);
    }
}
