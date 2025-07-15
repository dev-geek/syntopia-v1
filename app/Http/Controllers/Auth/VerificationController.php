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
        $this->middleware('throttle:6,1')->only('verifyCode', 'resend');
    }

    public function show()
    {
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

            $apiResponse = $this->callXiaoiceApiWithCreds($user, $user->subscriber_password);

            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                DB::rollBack();
                return back()->with('swal_error', $apiResponse['error_message']);
            }

            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                DB::rollBack();
                $user->delete(); // Delete user data on failure
                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                return redirect()->route('login')->with('error', $errorMsg);
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
            if (isset($user)) {
                $user->delete(); // Delete user data on failure
            }
            return redirect()->route('login')->withErrors([
                'server_error' => 'Something went wrong during verification. Please try again.'
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

    private function makeXiaoiceApiRequest(string $endpoint, array $data): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim(config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp'), '/');
        $fullUrl = $baseUrl . '/' . ltrim($endpoint, '/');

        return Http::timeout(30)
            ->connectTimeout(15)
            ->retry(3, 1000)
            ->withHeaders([
                'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post($fullUrl, $data);
    }

    private function callXiaoiceApiWithCreds(User $user, string $plainPassword): array
    {
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
            $createResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/create',
                [
                    'name' => $user->name,
                    'regionCode' => 'CN',
                    'adminName' => $user->name,
                    'adminEmail' => $user->email,
                    'adminPhone' => '',
                    'adminPassword' => $plainPassword,
                    'appIds' => [1],
                ]
            );

            $createJson = $createResponse->json();
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                return [
                    'success' => false,
                    'data' => null,
                    'error_message' => 'This admin is already registered with another company. Please use a different email or contact support.',
                    'swal' => true
                ];
            }

            if (!$createResponse->successful()) {
                return $this->handleFailedTenantCreation($createResponse, $user);
            }

            Log::info('Tenant created successfully', [
                'user_id' => $user->id,
                'response' => $createResponse->json()
            ]);

            $tenantId = $createResponse->json()['data']['tenantId'] ?? null;
            if (!$tenantId) {
                Log::error('Failed to extract tenantId from create response', [
                    'user_id' => $user->id,
                    'response' => $createResponse->json()
                ]);
                return [
                    'success' => false,
                    'data' => null,
                    'error_message' => 'Failed to create tenant. Missing tenantId in response.'
                ];
            }

            // Continue with password binding
            $passwordBindResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/user/password/bind',
                [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]
            );

            if (!$passwordBindResponse->successful()) {
                return $this->handleFailedPasswordBind($passwordBindResponse, $user);
            }

            $bindJson = $passwordBindResponse->json();
            if (!isset($bindJson['code']) || $bindJson['code'] != 200) {
                $errorMessage = $this->translateXiaoiceError(
                    $bindJson['code'] ?? null,
                    $bindJson['message'] ?? 'Password bind failed'
                );

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

            Log::info('Password bound successfully', [
                'user_id' => $user->id,
                'response' => $bindJson
            ]);

            return [
                'success' => true,
                'data' => $createResponse->json()['data'] ?? null,
                'error_message' => null
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

    private function handleFailedTenantCreation(\Illuminate\Http\Client\Response $response, User $user): array
    {
        $status = $response->status();
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
            'response_body' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage
        ];
    }

    private function handleFailedPasswordBind(\Illuminate\Http\Client\Response $response, User $user): array
    {
        $status = $response->status();
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
            'response' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage
        ];
    }

    private function translateXiaoiceError(?int $code, string $defaultMessage): string
    {
        return match ($code) {
            665 => 'The application is not activated for this tenant. Please contact support.',
            730 => 'This admin is already registered with another company.',
            400 => 'Invalid request parameters.',
            401 => 'Authentication failed.',
            404 => 'Resource not found.',
            429 => 'Too many requests. Please try again later.',
            500 => 'Internal server error.',
            default => $defaultMessage
        };
    }

    private function validatePasswordFormat(string $password): bool
    {
        $pattern = '/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/';
        return preg_match($pattern, $password) === 1;
    }
}
