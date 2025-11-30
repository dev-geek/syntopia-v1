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
use App\Services\MailService;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/user/dashboard';

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

    public function verifyCode(Request $request, \App\Services\SubscriptionService $subscriptionService)
    {
        Log::info('[verifyCode] Incoming request', [
            'input' => $request->all(),
            'session_email' => session('email'),
        ]);
        $request->validate([
            'verification_code' => 'required|string|size:6',
            'email' => 'required|email',
        ]);

        $email = session('email');
        if (!$email && $request->has('email')) {
            $email = $request->input('email');
            session(['email' => $email]);
            Log::info('[verifyCode] Set email from request', ['email' => $email]);
        }
        if (!$email) {
            Log::error('[verifyCode] No email in session or request');
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            Log::error('[verifyCode] User not found', ['email' => $email]);
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        // Check if user is already verified - treat as success
        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            Log::info('[verifyCode] User already verified (idempotent operation)', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If user has tenant_id, they're fully set up - just login
            if ($user->tenant_id) {
                session()->forget('email');
                Auth::login($user);

                if ($user->hasRole('User')) {
                    if (session()->has('verification_intended_url')) {
                        $intendedUrl = session('verification_intended_url');
                        session()->forget('verification_intended_url');
                        return redirect()->to($intendedUrl)
                            ->with('success', 'Email already verified!');
                    }

                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')
                            ->with('success', 'Email already verified!');
                    } else {
                        return redirect()->route('subscription')
                            ->with('success', 'Email already verified!');
                    }
                }

                if ($user->hasRole('Super Admin')) {
                    return redirect()->route('admin.dashboard')
                        ->with('success', 'Email already verified!');
                }
            }

            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        if ($user->verification_code !== $request->verification_code) {
            Log::error('[verifyCode] Invalid verification code', [
                'user_id' => $user->id,
                'expected' => $user->verification_code,
                'provided' => $request->verification_code
            ]);
            return back()->withErrors(['verification_code' => 'Invalid verification code.']);
        }

        if (!$user->subscriber_password) {
            Log::error('[verifyCode] No subscriber_password found for user during verification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return back()->withErrors([
                'server_error' => 'Password data missing. Please contact support.'
            ]);
        }

        try {
            DB::beginTransaction();

            // Check if user already has a tenant_id (from a previous partial registration)
            if ($user->tenant_id) {
                Log::info('[verifyCode] User already has tenant_id, skipping tenant creation', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id
                ]);

                // Just verify the email and update status
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 1,
                    'verification_code' => null,
                ]);

                DB::commit();
                session()->forget('email');
                Auth::login($user);

                if ($user->hasRole('User')) {
                    if (session()->has('verification_intended_url')) {
                        $intendedUrl = session('verification_intended_url');
                        session()->forget('verification_intended_url');
                        return redirect()->to($intendedUrl)
                            ->with('success', 'Email verified successfully!');
                    }

                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')
                            ->with('success', 'Email verified successfully!');
                    } else {
                        return redirect()->route('subscription')
                            ->with('success', 'Email verified successfully!');
                    }
                }

                if ($user->hasRole('Super Admin')) {
                    return redirect()->route('admin.dashboard')
                        ->with('success', 'Email verified successfully!');
                }

                return redirect()->route('login')
                    ->with('success', 'Email verified successfully! You can now login.');
            }

            Log::info('[verifyCode] Calling callXiaoiceApiWithCreds', ['user_id' => $user->id]);
            $apiResponse = $this->callXiaoiceApiWithCreds($user, $user->subscriber_password);
            Log::info('[verifyCode] callXiaoiceApiWithCreds response', ['user_id' => $user->id, 'apiResponse' => $apiResponse]);

            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                DB::rollBack();
                Log::error('[verifyCode] API returned swal error', ['user_id' => $user->id, 'error' => $apiResponse['error_message']]);

                // Check if error is due to user already being registered in tenant system
                if (str_contains($apiResponse['error_message'] ?? '', 'already registered')) {
                    // Check if there's another user with same email that has tenant_id
                    $existingUserWithTenant = User::where('email', $user->email)
                        ->whereNotNull('tenant_id')
                        ->where('id', '!=', $user->id)
                        ->first();

                    if ($existingUserWithTenant) {
                        Log::warning('[verifyCode] Duplicate user detected with existing tenant', [
                            'current_user_id' => $user->id,
                            'existing_user_id' => $existingUserWithTenant->id,
                            'tenant_id' => $existingUserWithTenant->tenant_id
                        ]);

                        // Delete the duplicate user
                        $user->delete();
                        return redirect()->route('login')
                            ->with('error', 'An account with this email already exists. Please login instead.');
                    }
                }

                return back()->with('verification_swal_error', $apiResponse['error_message']);
            }

            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                DB::rollBack();
                Log::error('[verifyCode] API failed or missing tenantId', [
                    'user_id' => $user->id,
                    'apiResponse' => $apiResponse
                ]);

                // Don't delete user if error is "already registered" - might be recoverable
                if (!str_contains($apiResponse['error_message'] ?? '', 'already registered')) {
                    $user->delete();
                }

                $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                return redirect()->route('login')->with('error', $errorMsg);
            }

            $user->update([
                'email_verified_at' => now(),
                'status' => 1,
                'verification_code' => null,
                'tenant_id' => $apiResponse['data']['tenantId'],
            ]);

            // After tenant is created and password bound, assign Free package with license
            // Only assign if user hasn't purchased paid packages
            $user->refresh();
            $user->load('package', 'userLicence', 'orders');

            // Check if user has ever purchased a paid package
            $hasPaidPackageOrder = $user->orders()
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->whereHas('package', function ($query) {
                    $query->whereRaw('LOWER(name) != ?', ['free']);
                })
                ->exists();

            // Only assign Free package if user hasn't purchased paid packages
            if (!$hasPaidPackageOrder) {
                // Check if user has a paid package assigned (package exists and name != 'free')
                $hasPaidPackage = $user->package && strtolower($user->package->name) !== 'free';

                if (!$hasPaidPackage) {
                    $freePackage = \App\Models\Package::where(function ($query) {
                        $query->where('price', 0)
                            ->orWhereRaw('LOWER(name) = ?', ['free']);
                    })->first();

                    if (!$freePackage) {
                        Log::error('[verifyCode] Free package not found during verification', [
                            'user_id' => $user->id,
                            'tenant_id' => $user->tenant_id,
                        ]);
                        throw new \Exception('Free package is not configured. Please contact support.');
                    }

                    $subscriptionService->assignFreePlanImmediately($user, $freePackage);
                    Log::info('[verifyCode] Free package assigned during email verification', ['user_id' => $user->id]);
                } else {
                    Log::info('[verifyCode] Skipping Free package assignment - user has paid package', [
                        'user_id' => $user->id,
                        'package_name' => $user->package->name,
                    ]);
                }
            } else {
                Log::info('[verifyCode] Skipping Free package assignment - user has purchased paid packages', [
                    'user_id' => $user->id,
                ]);
            }

            DB::commit();
            Log::info('[verifyCode] User verified and updated', ['user_id' => $user->id]);

            session()->forget('email');
            Auth::login($user);

            if ($user->hasRole('User')) {
                // Check if there's an intended URL from the subscription flow
                if (session()->has('verification_intended_url')) {
                    $intendedUrl = session('verification_intended_url');
                    session()->forget('verification_intended_url');
                    Log::info('[verifyCode] Redirecting to intended URL', [
                        'user_id' => $user->id,
                        'intended_url' => $intendedUrl
                    ]);
                    return redirect()->to($intendedUrl)
                        ->with('success', 'Email verified successfully!');
                }

                // Check if user has active subscription
                if ($this->hasActiveSubscription($user)) {
                    Log::info('[verifyCode] Redirecting to user dashboard', ['user_id' => $user->id]);
                    return redirect()->route('user.dashboard')
                        ->with('success', 'Email verified successfully!');
                } else {
                    Log::info('[verifyCode] Redirecting to subscription', ['user_id' => $user->id]);
                    return redirect()->route('subscription')
                        ->with('success', 'Email verified successfully!');
                }
            }
            if ($user->hasRole('Super Admin')) {
                Log::info('[verifyCode] Redirecting to admin.dashboard', ['user_id' => $user->id]);
                return redirect()->route('admin.dashboard')
                    ->with('success', 'Email verified successfully!');
            }
            Log::info('[verifyCode] Redirecting to login (fallback)', ['user_id' => $user->id]);
            return redirect()->route('login')
                ->with('success', 'Email verified successfully! You can now login.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[verifyCode] Exception during verification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
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

            // Send verification email with proper error handling
            $mailResult = MailService::send($user->email, new VerifyEmail($user));

            if ($mailResult['success']) {
                Log::info('Verification email resent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return back()->with('message', 'Verification code has been resent');
            } else {
                Log::error('Failed to resend verification email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailResult['error'] ?? 'Unknown error'
                ]);

                // Store the verification code in session as fallback
                session(['verification_code' => $user->verification_code]);
                return back()->withErrors(['mail_error' => $mailResult['message']]);
            }
        }

        return back()->withErrors(['mail_error' => 'User not found. Please try logging in again.']);
    }

    /**
     * Check if user has an active subscription
     */
    private function hasActiveSubscription($user)
    {
        if (!$user->is_subscribed || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        // Check if user has an active license
        $activeLicense = $user->userLicence;
        if (!$activeLicense || !$activeLicense->isActive()) {
            return false;
        }

        // Check if license is not expired
        if ($activeLicense->isExpired()) {
            return false;
        }

        return true;
    }

    public function deleteUserAndRedirect(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        if ($user) {
            Log::info('[deleteUserAndRedirect] Deleting user', ['user_id' => $user->id, 'email' => $email]);
            $user->delete();
        } else {
            Log::warning('[deleteUserAndRedirect] User not found', ['email' => $email]);
        }
        session()->forget('email');
        return redirect()->route('login')->with('error', 'Your account was not created. Please register again with a different email.');
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
            // Handle case where tenant already exists (code 730)
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                Log::info('Tenant already exists for user (idempotent operation)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'api_code' => $createJson['code']
                ]);

                // Try to find if there's another user with same email that has tenant_id
                $existingUserWithTenant = User::where('email', $user->email)
                    ->whereNotNull('tenant_id')
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUserWithTenant) {
                    Log::info('Found existing user with tenant for same email, using existing tenant_id', [
                        'current_user_id' => $user->id,
                        'existing_user_id' => $existingUserWithTenant->id,
                        'tenant_id' => $existingUserWithTenant->tenant_id
                    ]);

                    // Use the existing tenant_id - this is idempotent
                    $tenantId = $existingUserWithTenant->tenant_id;
                } else {
                    // Tenant exists in external system but we don't have tenant_id in our DB
                    // This could happen if a previous registration partially succeeded
                    // Try to extract tenant_id from response if available
                    $tenantId = $createJson['data']['tenantId'] ?? null;

                    if (!$tenantId) {
                        Log::warning('Tenant exists but tenant_id not found in response or database', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'response' => $createJson
                        ]);

                        // Return error but don't treat as fatal - might be recoverable
                        return [
                            'success' => false,
                            'data' => null,
                            'error_message' => 'This user is already registered. Please use a different email or contact support.',
                            'swal' => true
                        ];
                    }
                }

                // Continue with password binding using existing tenant_id
                Log::info('Proceeding with password binding for existing tenant', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
            } else {
                // Normal flow: tenant was created successfully
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
            }

            // Password binding - can be called multiple times safely
            $passwordBindResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/user/password/bind',
                [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]
            );

            $bindJson = $passwordBindResponse->json();

            // Handle various success scenarios
            // Some APIs return success codes even if already bound - treat as success
            $isSuccess = $passwordBindResponse->successful() &&
                        (isset($bindJson['code']) && in_array($bindJson['code'], [200, 201]));

            if (!$isSuccess) {
                // Check if error is due to password already being bound (idempotent case)
                $errorMessage = $bindJson['message'] ?? '';
                $errorCode = $bindJson['code'] ?? null;

                // If password is already bound with same value, treat as success
                if (str_contains(strtolower($errorMessage), 'already') ||
                    str_contains(strtolower($errorMessage), 'bound') ||
                    $errorCode == 200) {
                    Log::info('Password already bound', [
                        'user_id' => $user->id,
                        'response' => $bindJson
                    ]);
                    // Treat as success - password binding is idempotent
                } else {
                    // Real error occurred
                    if (!$passwordBindResponse->successful()) {
                        return $this->handleFailedPasswordBind($passwordBindResponse, $user);
                    }

                    $errorMessage = $this->translateXiaoiceError(
                        $errorCode,
                        $errorMessage ?: 'Password bind failed'
                    );

                    Log::error('Failed to bind password', [
                        'user_id' => $user->id,
                        'response' => $bindJson
                    ]);

                    return [
                        'success' => false,
                        'data' => null,
                        'error_message' => $errorMessage,
                        'swal' => true
                    ];
                }
            }

            Log::info('Password bound successfully (idempotent)', [
                'user_id' => $user->id,
                'response' => $bindJson
            ]);

            return [
                'success' => true,
                'data' => ['tenantId' => $tenantId],
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
            'error_message' => $errorMessage,
            'swal' => true
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
            'error_message' => $errorMessage,
            'swal' => true
        ];
    }

    private function handleFailedAddLicense(\Illuminate\Http\Client\Response $response, User $user): array
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

        Log::error('Failed to add license', [
            'user_id' => $user->id,
            'status' => $status,
            'response' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage,
            'swal' => true
        ];
    }

    private function translateXiaoiceError(?int $code, string $defaultMessage): string
    {
        return match ($code) {
            665 => 'The application is not activated for this tenant. Please contact support.',
            730 => 'This user is already registered. Please use a different email or contact support.',
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
