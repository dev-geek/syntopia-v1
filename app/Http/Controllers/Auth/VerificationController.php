<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/pricing';

    public function __construct()
    {
        $this->middleware('web');
        // Removed 'signed' middleware as we're using custom verification codes, not signed URLs
        $this->middleware('throttle:6,1')->only('verifyCode', 'resend');
    }

    public function show()
    {
        // Get email from session
        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        // Check if user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        // Check if already verified
        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|size:6'
        ]);

        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        if ($user->verification_code !== $request->verification_code) {
            return back()->withErrors(['verification_code' => 'Invalid verification code.']);
        }

        try {
            DB::beginTransaction();

            // Call API
            $apiResponse = $this->callXiaoiceApiWithCreds($user, $request->password);

            if (!$apiResponse || empty($apiResponse['data']['tenantId'])) {
                // API failed: rollback and prompt re-verification
                DB::rollBack();

                return back()->with('error', 'System API is down right now. Please try again later.');
            }

            $user->update([
                'email_verified_at' => now(),
                'status' => 1,
                'verification_code' => null,
                'tenant_id' => $apiResponse['data']['tenantId'],
            ]);

            DB::commit();

            session()->forget('email');

            Auth::login($user);

            if ($user->hasRole('User')) {
                return redirect()->route('subscriptions.index')
                    ->with('success', 'Email verified successfully!');
            }

            return redirect()->route('login')
                ->with('success', 'Email verified successfully! You can now login.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'server_error' => 'Something went wrong during verification. Please try again later.'
            ]);
        }
    }

    public function resend()
    {
        $user = User::where('email', session('email'))->first();

        if ($user) {
            $user->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->save();

            Mail::to($user->email)->send(new VerifyEmail($user));
        }

        return back()->with('message', 'Verification code has been resent');
    }

    private function callXiaoiceApiWithCreds($user, $plainPassword)
    {
        try {
            $response = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/create', [
                'name' => $user->name,
                'regionCode' => 'OTHER',
                'adminName' => $user->name,
                'adminEmail' => $user->email,
                'adminPassword' => $plainPassword,
                'appIds' => [2],
            ]);

            if ($response->successful()) {
                return $response->json(); // ✅ Return the actual response data
            }

            // Log error if not successful
            $status = $response->status();
            $errorMessage = match ($status) {
                400 => 'Bad Request - Missing required parameters.',
                401 => 'Unauthorized - Invalid or expired subscription key.',
                404 => 'Not Found - The requested resource does not exist.',
                429 => 'Too Many Requests - Rate limit exceeded.',
                500 => 'Internal Server Error - API server issue.',
                default => 'Unexpected error occurred.'
            };

            Log::error('Xiaoice API call failed', [
                'user_id' => $user->id,
                'status' => $status,
                'error_message' => $errorMessage,
                'response_body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API', [
                'user_id' => $user->id,
                'exception_message' => $e->getMessage()
            ]);
        }

        return null;
    }
}
