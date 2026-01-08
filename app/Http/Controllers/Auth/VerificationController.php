<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\Verification\VerificationService;
use App\Services\Auth\Verification\VerificationEmailService;
use App\Services\Auth\Verification\VerificationSessionService;
use App\Services\AuthRedirectService;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Requests\Auth\DeleteUserRequest;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/user/dashboard';

    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('throttle:6,1')->only('verifyCode', 'resend');
    }

    public function show(VerificationSessionService $sessionService)
    {
        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = $sessionService->getUserByEmail($email);
        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        if ($sessionService->isUserAlreadyVerified($user)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    public function verifyCode(
        VerifyCodeRequest $request,
        VerificationService $verificationService,
        VerificationSessionService $sessionService,
        AuthRedirectService $redirectService
    ) {
        $email = $sessionService->getEmailFromSessionOrRequest($request);
        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = $sessionService->getUserByEmail($email);
        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        if ($sessionService->isUserAlreadyVerified($user)) {
            if ($user->tenant_id) {
                $sessionService->clearEmailSession();
                Auth::login($user);
                return $redirectService->getRedirectForUser($user, 'Email already verified!');
            }

            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        try {
            $result = $verificationService->verifyCode($user, $request->verification_code);

            if (!$result['success']) {
                return $this->handleVerificationFailure($result);
            }

            $user = $result['user'];
            $sessionService->clearEmailSession();
            Auth::login($user);

            return $redirectService->getRedirectForUser($user, 'Email verified successfully!');

        } catch (\Exception $e) {
            return $this->handleVerificationException($e, $user ?? null);
        }
    }

    private function handleVerificationFailure(array $result)
    {
        if (isset($result['swal']) && $result['swal']) {
            return back()->with('verification_swal_error', $result['error']);
        }

        if (isset($result['redirect']) && $result['redirect'] === 'login') {
            return redirect()->route('login')->with('error', $result['error']);
        }

        return back()->withErrors(['server_error' => $result['error']]);
    }

    private function handleVerificationException(\Exception $e, ?User $user)
    {
        Log::error('[verifyCode] Exception during verification', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $user->id ?? null
        ]);

        if ($user) {
            $user->delete();
        }

        return redirect()->route('login')->withErrors([
            'server_error' => 'Something went wrong during verification. Please try again.'
        ]);
    }

    public function resend(VerificationSessionService $sessionService, VerificationEmailService $emailService)
    {
        $email = session('email');
        if (!$email) {
            return back()->withErrors(['mail_error' => 'User not found. Please try logging in again.']);
        }

        $user = $sessionService->getUserByEmail($email);
        if (!$user) {
            return back()->withErrors(['mail_error' => 'User not found. Please try logging in again.']);
        }

        $result = $emailService->resendVerificationCode($user);

        if ($result['success']) {
            return back()->with('message', $result['message']);
        }

        return back()->withErrors(['mail_error' => $result['error']]);
    }

    public function deleteUserAndRedirect(DeleteUserRequest $request, VerificationSessionService $sessionService)
    {
        $email = $request->input('email');
        $user = $sessionService->getUserByEmail($email);

        if ($user) {
            $user->delete();
        } else {
            Log::warning('[deleteUserAndRedirect] User not found', ['email' => $email]);
        }

        $sessionService->clearEmailSession();
        return redirect()->route('login')->with('error', 'Your account was not created. Please register again with a different email.');
    }
}
