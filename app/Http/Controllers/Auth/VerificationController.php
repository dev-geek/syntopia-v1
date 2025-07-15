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

        // Check if subscriber_password exists
        if (!$user->subscriber_password) {
            Log::error('No subscriber_password found for user during verification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return back()->withErrors([
                'server_error' => 'Password data missing. Please contact support.'
            ]);
        }

        try {
            DB::beginTransaction();

            // Call API
            $apiResponse = $this->callXiaoiceApiWithCreds($user, $user->subscriber_password);

            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                return back()->with('swal_error', $apiResponse['error_message']);
            }

            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                // API failed: rollback and prompt re-verification
                DB::rollBack();
                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                return back()->with('error', $errorMsg);
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
                return redirect()->route('pricing')
                    ->with('success', 'Email verified successfully!');
            }
            if ($user->hasRole('Super Admin') || $user->hasRole('Sub Admin')) {
                return redirect()->route('admin.dashboard')
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

    private function callXiaoiceApiWithCreds(User $user, string $plainPassword): array
    {
        // Validate password format before sending to API
        if (!$this->validatePasswordFormat($plainPassword)) {
            Log::error('Password format validation failed before API call', [
                'user_id' => $user->id,
                'password_length' => strlen($plainPassword)
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => 'Password format is invalid. Please contact support.'
            ];
        }

        try {
            // Create the tenant
            $createResponse = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/create', [
                'name' => $user->name,
                'regionCode' => 'CN',
                'adminName' => $user->name,
                'adminEmail' => $user->email,
                'adminPhone' => '',
                'adminPassword' => $plainPassword, // direct activation
                'appIds' => [1],
            ]);

            $createJson = $createResponse->json();
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_message' => 'This admin is already registered with another company. Please use a different email or contact support.',
                    'swal' => true
                ];
            }

            if ($createResponse->successful()) {
                Log::info('Tenant created successfully', [
                    'user_id' => $user->id,
                    'response' => $createResponse->json()
                ]);

                // Bind password using password bind API
                $passwordBindResponse = Http::withHeaders([
                    'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/user/password/bind', [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]);

                if ($passwordBindResponse->successful()) {
                    $bindJson = $passwordBindResponse->json();
                    if (isset($bindJson['code']) && $bindJson['code'] == 200) {
                        Log::info('Password bound successfully', [
                            'user_id' => $user->id,
                            'response' => $bindJson
                        ]);
                        return [
                            'success' => true,
                            'data' => $createResponse->json()['data'] ?? null,
                            'error_message' => null
                        ]; // success
                    } else {
                        $errorMessage = '[' . ($bindJson['code'] ?? 'unknown') . '] ' . ($bindJson['message'] ?? 'Password bind failed');
                        // Translate Chinese password error to English for user clarity
                        if (str_contains($errorMessage, '密码格式错误') || str_contains($errorMessage, 'Missing required parameters')) {
                            // Log the real error, but show a generic error to the user
                            Log::error('API returned registration-related error at verification', [
                                'user_id' => $user->id,
                                'response' => $bindJson,
                                'shown_to_user' => 'System error. Please contact support.'
                            ]);
                            $errorMessage = 'System error. Please contact support.';
                        }
                        Log::error('Failed to bind password', [
                            'user_id' => $user->id,
                            'response' => $bindJson
                        ]);
                        return [
                            'success' => false,
                            'data' => null,
                            'error_message' => $errorMessage
                        ];
                    }
                } else {
                    $status = $passwordBindResponse->status();
                    $errorMessage = "[$status] " . match ($status) {
                        400 => 'Bad Request - Missing required parameters.',
                        401 => 'Unauthorized - Invalid or expired subscription key.',
                        404 => 'Not Found - The requested resource does not exist.',
                        429 => 'Too Many Requests - Rate limit exceeded.',
                        500 => 'Internal Server Error - API server issue.',
                        default => 'Unexpected error occurred.'
                    };
                    Log::error('Failed to bind password', [
                        'user_id' => $user->id,
                        'status' => $status,
                        'response' => $passwordBindResponse->body()
                    ]);
                    return [
                        'success' => false,
                        'data' => null,
                        'error_message' => $errorMessage
                    ];
                }
            }

            // Tenant creation failed
            $status = $createResponse->status();
            $errorMessage = "[$status] " . match ($status) {
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
                'response_body' => $createResponse->body()
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API', [
                'user_id' => $user->id,
                'exception_message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => 'System error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate password format according to Xiaoice API requirements
     */
    private function validatePasswordFormat(string $password): bool
    {
        // API regex: ^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$
        $pattern = '/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/';
        return preg_match($pattern, $password) === 1;
    }
}
