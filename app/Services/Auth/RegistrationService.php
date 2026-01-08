<?php

namespace App\Services\Auth;

use App\Models\{User, PaymentGateways};
use App\Factories\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DeviceFingerprintService;
use App\Services\FreePlanAbuseService;
use App\Services\MailService;
use App\Mail\VerifyEmail;

class RegistrationService
{
    public function __construct(
        private DeviceFingerprintService $deviceFingerprintService,
        private FreePlanAbuseService $freePlanAbuseService,
        private PaymentGatewayFactory $paymentGatewayFactory,
    ) {}

    public function validateRegistration(Request $request): array
    {
        if (config('free_plan_abuse.enabled', false)) {
            $isBlocked = $this->deviceFingerprintService->isBlocked($request);
            $hasRecentAttempts = $this->deviceFingerprintService->hasRecentAttempts(
                $request,
                config('free_plan_abuse.max_attempts', 1),
                config('free_plan_abuse.tracking_period_days', 9999999999999)
            );

            if ($isBlocked || $hasRecentAttempts) {
                Log::warning('Registration blocked due to fingerprint abuse', [
                    'ip' => $request->ip(),
                    'email' => $request->input('email'),
                    'fingerprint_id' => $request->input('fingerprint_id'),
                    'is_blocked' => $isBlocked,
                    'has_recent_attempts' => $hasRecentAttempts
                ]);

                return [
                    'success' => false,
                    'error' => 'Registration is not allowed from this device. Please contact support if you believe this is an error.'
                ];
            }
        }

        if (config('free_plan_abuse.enabled', false)) {
            $abuseCheck = $this->freePlanAbuseService->checkAbusePatterns($request);
            if (!$abuseCheck['allowed']) {
                Log::warning('Registration blocked due to abuse patterns', [
                    'reason' => $abuseCheck['reason'],
                    'ip' => $request->ip(),
                    'email' => $request->input('email'),
                    'user_agent' => $request->userAgent()
                ]);

                return [
                    'success' => false,
                    'error' => 'email',
                    'message' => $abuseCheck['message']
                ];
            }
        }

        try {
            $this->deviceFingerprintService->recordAttempt($request);
        } catch (\Exception $e) {
            Log::error('Failed to record fingerprint attempt: ' . $e->getMessage(), [
                'exception' => $e,
                'ip' => $request->ip(),
                'email' => $request->input('email')
            ]);
        }

        return ['success' => true];
    }

    public function registerUser(Request $request): array
    {
        try {
            DB::beginTransaction();

            $existingUser = User::where('email', $request->email)->lockForUpdate()->first();
            if ($existingUser) {
                DB::rollBack();
                if (!$existingUser->email_verified_at) {
                    return [
                        'success' => true,
                        'action' => 'login_and_redirect',
                        'user' => $existingUser,
                        'route' => '/email/verify'
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'email',
                    'message' => 'This email is already registered. Please login instead.'
                ];
            }

            $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $full_name = $request->first_name . ' ' . $request->last_name;

            try {
                $user = User::create([
                    'email' => $request->email,
                    'name' => $full_name,
                    'password' => $request->password,
                    'subscriber_password' => $request->password,
                    'verification_code' => $verification_code,
                    'verification_code_sent_at' => now(),
                    'email_verified_at' => null,
                    'status' => 0
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    DB::rollBack();
                    $existingUser = User::where('email', $request->email)->first();
                    if ($existingUser && !$existingUser->email_verified_at) {
                        return [
                            'success' => true,
                            'action' => 'login_and_redirect',
                            'user' => $existingUser,
                            'route' => '/email/verify'
                        ];
                    }

                    return [
                        'success' => false,
                        'error' => 'email',
                        'message' => 'This email is already registered. Please login instead.'
                    ];
                }
                throw $e;
            }

            $user->assignRole('User');

            $this->assignPaymentGatewayToUser($user);

            DB::commit();

            $mailResult = MailService::send($user->email, new VerifyEmail($user));

            if ($mailResult['success']) {
                } else {
                Log::warning('Failed to send verification email during registration', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailResult['error'] ?? 'Unknown error'
                ]);

                session(['mail_error' => $mailResult['message']]);
                session(['verification_code' => $verification_code]);
            }

            return [
                'success' => true,
                'user' => $user,
                'mail_sent' => $mailResult['success'] ?? false
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User registration failed and rolled back', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function assignPaymentGatewayToUser(User $user, string $logPrefix = ''): void
    {
        $paddleGateway = PaymentGateways::active()->byName('Paddle')->first();
        if ($paddleGateway) {
            try {
                $paddlePaymentGateway = $this->paymentGatewayFactory->create('Paddle');
                $paddlePaymentGateway->setUser($user);
                $paddleCustomerId = $paddlePaymentGateway->createOrGetCustomer($user);

                if ($paddleCustomerId) {
                    }
            } catch (\Exception $e) {
                Log::warning(($logPrefix ? $logPrefix . ' ' : '') . 'Failed to create Paddle customer ID during registration', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $activeGateway = PaymentGateways::where('is_active', true)->first();
        if ($activeGateway && !$user->payment_gateway_id) {
            $user->update(['payment_gateway_id' => $activeGateway->id]);
            }
    }

}
