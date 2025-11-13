<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Services\PasswordBindingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;


class SocialController extends Controller
{
    public function googleLogin()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleAuthentication(PasswordBindingService $passwordBindingService)
    {
        try {
            // Get the user information from Google
            $googleUser = Socialite::driver('google')->user();

            // Check if a user with the Google ID already exists
            $user = User::where('google_id', $googleUser->id)->first();

            if ($user) {
                // Log in the existing user
                Auth::login($user);
                if ($user->hasAnyRole(['Super Admin'])) {
                    return redirect()->route('admin.dashboard')->with('login_success', 'Admin Login Successfully');
                }

                // For regular users, check subscription status
                if ($user->hasRole('User')) {
                    if ($this->hasActiveSubscription($user)) {
                        return redirect()->route('user.dashboard')->with('login_success', 'User Login Successfully');
                    } else {
                        return redirect()->route('subscription')->with('login_success', 'User Login Successfully');
                    }
                }

                return redirect()->route('user.profile')->with('login_success', 'User Login Successfully');

            } else {
                // Check if a user with the same email exists
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Try to link Google account with existing user
                    try {
                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the existing user
                        $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for existing user during Google link, proceeding with fallback', [
                                'user_id' => $existingUser->id,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Check if this is a SWAL error
                            if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                                return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                            }

                            // Fallback: Link account without updating password
                            $existingUser->update([
                                'google_id' => $googleUser->id,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1
                            ]);

                            Auth::login($existingUser);

                            return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Note: You may need to update your password later for full functionality.');
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Google successfully!');

                    } catch (\Exception $e) {
                        Log::error('Error during Google account linking', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback: Link account without password update
                        $existingUser->update([
                            'google_id' => $googleUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Google account linked successfully! Please update your password in your profile for full functionality.');
                    }
                } else {
                    // Create new user with tenant creation
                    try {
                        DB::beginTransaction();

                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Create user first
                        $userData = User::create([
                            'name' => $googleUser->name,
                            'email' => $googleUser->email,
                            'google_id' => $googleUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => null, // Set to NULL for first-time Google registration
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $userData->assignRole('User');

                        // Create tenant and bind password using the same logic as VerificationController
                        Log::info('[googleAuthentication] Calling callXiaoiceApiWithCreds for new Google user', ['user_id' => $userData->id]);
                        $apiResponse = $this->callXiaoiceApiWithCreds($userData, $compliantPassword);
                        Log::info('[googleAuthentication] callXiaoiceApiWithCreds response', ['user_id' => $userData->id, 'apiResponse' => $apiResponse]);

                        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
                            DB::rollBack();
                            Log::error('[googleAuthentication] API returned swal error', ['user_id' => $userData->id, 'error' => $apiResponse['error_message']]);
                            return redirect()->route('login')->with('swal_error', $apiResponse['error_message']);
                        }

                        if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                            DB::rollBack();
                            Log::error('[googleAuthentication] API failed or missing tenantId', [
                                'user_id' => $userData->id,
                                'apiResponse' => $apiResponse
                            ]);
                            $userData->delete(); // Delete user data on failure
                            $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
                            return redirect()->route('login')->with('error', $errorMsg);
                        }

                        // Update user with tenant_id
                        $userData->update([
                            'tenant_id' => $apiResponse['data']['tenantId'],
                        ]);

                        DB::commit();
                        Log::info('[googleAuthentication] New Google user created with tenant', ['user_id' => $userData->id]);

                        Auth::login($userData);
                        return $this->redirectBasedOnUserRole($userData, 'Welcome! Account created successfully with Google');

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('[googleAuthentication] Exception during new Google user creation', [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email' => $googleUser->email
                        ]);

                        if (isset($userData)) {
                            $userData->delete(); // Delete user data on failure
                        }

                        return redirect()->route('login')->with('error', 'Failed to create account. Please try again or contact support.');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Google Authentication Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'We encountered an issue connecting to Google. Please try again or use email login instead.')
                ->withInput();
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback(PasswordBindingService $passwordBindingService)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();

            // Check if user with Facebook ID exists
            $user = User::where('facebook_id', $facebookUser->id)->first();

            if ($user) {
                Auth::login($user);
                return $this->redirectBasedOnUserRole($user, 'User Login Successfully');
            } else {
                // Check if user with same email exists
                $existingUser = User::where('email', $facebookUser->email)->first();

                if ($existingUser) {
                    // Try to link Facebook account with existing user
                    try {
                        // Generate a compliant password for the existing user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the existing user
                        $apiResponse = $passwordBindingService->bindPassword($existingUser, $compliantPassword);

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for existing user during Facebook link, proceeding with fallback', [
                                'user_id' => $existingUser->id,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Link account without updating password
                            $existingUser->update([
                                'facebook_id' => $facebookUser->id,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1
                            ]);

                            Auth::login($existingUser);
                            return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Note: You may need to update your password later for full functionality.');
                        }

                        // Success: Update password and link account
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Account linked with Facebook successfully!');

                    } catch (\Exception $e) {
                        Log::error('Error during Facebook account linking', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback: Link account without password update
                        $existingUser->update([
                            'facebook_id' => $facebookUser->id,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1
                        ]);

                        Auth::login($existingUser);
                        return $this->redirectBasedOnUserRole($existingUser, 'Facebook account linked successfully! Please update your password in your profile for full functionality.');
                    }
                } else {
                    // Create new user
                    try {
                        // Generate a compliant password for the new user
                        $compliantPassword = $this->generateCompliantPassword();

                        // Call password binding API for the new user
                        $apiResponse = $passwordBindingService->bindPassword(
                            (new User())->forceFill(['email' => $facebookUser->email]),
                            $compliantPassword
                        );

                        if (!$apiResponse['success']) {
                            Log::warning('Failed to bind password for new Facebook user, proceeding with fallback', [
                                'email' => $facebookUser->email,
                                'error' => $apiResponse['error_message']
                            ]);

                            // Fallback: Create user with temporary password
                            $tempPassword = $this->generateCompliantPassword();
                            $newUser = User::create([
                                'name' => $facebookUser->name,
                                'email' => $facebookUser->email,
                                'facebook_id' => $facebookUser->id,
                                'password' => Hash::make($tempPassword),
                                'subscriber_password' => $tempPassword,
                                'email_verified_at' => Carbon::now(),
                                'status' => 1,
                                'verification_code' => null
                            ]);

                            $newUser->assignRole('User');
                            Auth::login($newUser);

                            return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook. Please update your password in your profile for full functionality.');
                        }

                        // Success: Create user with proper password
                        $newUser = User::create([
                            'name' => $facebookUser->name,
                            'email' => $facebookUser->email,
                            'facebook_id' => $facebookUser->id,
                            'password' => Hash::make($compliantPassword),
                            'subscriber_password' => $compliantPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $newUser->assignRole('User');
                        Auth::login($newUser);

                        return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook');

                    } catch (\Exception $e) {
                        Log::error('Error creating new Facebook user', [
                            'email' => $facebookUser->email,
                            'error' => $e->getMessage()
                        ]);

                        // Final fallback: Create user with basic info
                        $tempPassword = $this->generateCompliantPassword();
                        $newUser = User::create([
                            'name' => $facebookUser->name,
                            'email' => $facebookUser->email,
                            'facebook_id' => $facebookUser->id,
                            'password' => Hash::make($tempPassword),
                            'subscriber_password' => $tempPassword,
                            'email_verified_at' => Carbon::now(),
                            'status' => 1,
                            'verification_code' => null
                        ]);

                        $newUser->assignRole('User');
                        Auth::login($newUser);

                        return $this->redirectBasedOnUserRole($newUser, 'Welcome! Account created successfully with Facebook. Please update your password in your profile for full functionality.');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Facebook Authentication Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'We encountered an issue connecting to Facebook. Please try again or use email login instead.')
                ->withInput();
        }
    }

    /**
     * Generate a password that meets Xiaoice API requirements
     */
    private function generateCompliantPassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = ',.<>{}~!@#$%^&_';

        // Ensure at least one character from each required category
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]; // One uppercase
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)]; // One lowercase
        $password .= $numbers[random_int(0, strlen($numbers) - 1)]; // One number
        $password .= $special[random_int(0, strlen($special) - 1)]; // One special

        // Fill the rest with random characters from all categories
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < 12; $i++) { // Total length 12 characters
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to make it more random
        return str_shuffle($password);
    }

    /**
     * Redirect user based on their role and subscription status
     */
    private function redirectBasedOnUserRole($user, $message)
    {
        // Check for intended URL first
        if (session()->has('url.intended')) {
            $intendedUrl = session('url.intended');
            session()->forget('url.intended');
            
            // Validate URL based on user role
            // For regular users, only allow safe (non-admin) URLs
            if ($user->hasRole('User') && !$this->isUrlSafeForUser($intendedUrl)) {
                // Ignore unsafe intended URL for regular users, fall through to role-based redirect
            } else {
                // For admins, allow any URL; for users, only safe URLs
                return redirect()->to($intendedUrl)->with('login_success', $message);
            }
        }

        if ($user->hasAnyRole(['Super Admin'])) {
            return redirect()->route('admin.dashboard')->with('login_success', $message);
        }

        if ($user->hasRole('User')) {
            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard')->with('login_success', $message);
            } else {
                return redirect()->route('subscription')->with('login_success', $message);
            }
        }

        return redirect()->route('user.profile')->with('login_success', $message);
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

    /**
     * Check if a URL is safe for regular users (not admin routes)
     */
    private function isUrlSafeForUser(string $url): bool
    {
        // Parse the URL to get the path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        // Block admin routes
        if (str_starts_with($path, '/admin')) {
            return false;
        }

        // Allow other routes (user routes, public routes, etc.)
        return true;
    }

    /**
     * Create tenant and bind password using Xiaoice API (same logic as VerificationController)
     */
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
                    'error_message' => 'This user is already registered. Please use a different email or contact support.',
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
                    'error_message' => $errorMessage,
                    'swal' => true
                ];
            }

            Log::info('Password bound successfully', [
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
