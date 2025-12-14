<?php

namespace App\Services;

use App\Models\User;
use App\Services\PasswordBindingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantAssignmentService
{
    private PasswordBindingService $passwordBindingService;

    public function __construct(PasswordBindingService $passwordBindingService)
    {
        $this->passwordBindingService = $passwordBindingService;
    }

    public function assignTenantWithRetry(User $user, ?string $plainPassword = null, int $maxAttempts = 3): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            Log::info('Tenant assignment attempt', [
                'user_id' => $user->id,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts
            ]);

            $result = $this->assignTenant($user, $plainPassword);

            if ($result['success'] && !empty($result['data']['tenantId'])) {
                Log::info('Tenant assignment succeeded', [
                    'user_id' => $user->id,
                    'attempt' => $attempt,
                    'tenant_id' => $result['data']['tenantId']
                ]);
                return $result;
            }

            $lastError = $result;

            if (isset($result['swal']) && $result['swal'] === true) {
                break;
            }

            if ($attempt < $maxAttempts) {
                $delay = $attempt * 500;
                Log::info('Waiting before tenant assignment retry', [
                    'user_id' => $user->id,
                    'attempt' => $attempt,
                    'delay_ms' => $delay
                ]);
                usleep($delay * 1000);
            }
        }

        Log::info('TenantAssignmentService final response', [
            'user_id' => $user->id,
            'attempts' => $attempt,
            'success' => $lastError['success'] ?? false,
            'apiResponse' => $lastError
        ]);

        return $lastError ?? [
            'success' => false,
            'data' => null,
            'error_message' => 'Tenant assignment failed after all retry attempts.',
            'swal' => false
        ];
    }

    public function assignTenant(User $user, ?string $plainPassword = null): array
    {
        if ($user->tenant_id) {
            return [
                'success' => true,
                'message' => 'User already has tenant_id',
                'tenant_id' => $user->tenant_id,
                'data' => ['tenantId' => $user->tenant_id]
            ];
        }

        $password = $plainPassword ?? $user->subscriber_password;

        if (!$password) {
            Log::warning('Cannot assign tenant - missing password', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return [
                'success' => false,
                'message' => 'User missing password',
                'error_message' => 'Cannot assign tenant_id without password',
                'swal' => true
            ];
        }

        if (!$this->validatePasswordFormat($password)) {
            Log::error('Password format validation failed before API call', [
                'user_id' => $user->id,
                'password_length' => strlen($password)
            ]);
            return [
                'success' => false,
                'message' => 'Password format is invalid',
                'error_message' => 'Password format is invalid. Please contact support.',
                'swal' => true
            ];
        }

        try {
            $apiResponse = $this->callXiaoiceApiWithCreds($user, $password);

            if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
                Log::warning('Tenant assignment failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'apiResponse' => $apiResponse
                ]);
                return [
                    'success' => false,
                    'message' => 'Failed to assign tenant_id',
                    'error_message' => $apiResponse['error_message'] ?? 'Unknown error',
                    'swal' => $apiResponse['swal'] ?? false
                ];
            }

            Log::info('Tenant_id assigned successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $apiResponse['data']['tenantId']
            ]);

            return [
                'success' => true,
                'message' => 'Tenant_id assigned successfully',
                'tenant_id' => $apiResponse['data']['tenantId'],
                'data' => $apiResponse['data']
            ];

        } catch (\Exception $e) {
            Log::error('Exception during tenant assignment', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Exception occurred',
                'error_message' => 'System error: ' . $e->getMessage(),
                'swal' => true
            ];
        }
    }

    private function callXiaoiceApiWithCreds(User $user, string $plainPassword): array
    {
        try {
            $createResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/create',
                [
                    'name' => $user->name,
                    'regionCode' => 'OTHER',
                    'adminName' => $user->name,
                    'adminEmail' => $user->email,
                    'adminPhone' => '',
                    'adminPassword' => $plainPassword,
                    'appIds' => [1],
                ]
            );

            $createJson = $createResponse->json();

            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], 'User is already registered in the system')) {
                Log::info('Tenant already exists for user (idempotent operation)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'api_code' => $createJson['code']
                ]);

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

                    $tenantId = $existingUserWithTenant->tenant_id;
                } else {
                    $tenantId = $createJson['data']['tenantId'] ?? null;

                    if (!$tenantId) {
                        Log::warning('Tenant exists but tenant_id not found in response or database', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'response' => $createJson
                        ]);

                        return [
                            'success' => false,
                            'data' => null,
                            'error_message' => 'This user is already registered. Please use a different email or contact support.',
                            'swal' => true
                        ];
                    }
                }

                Log::info('Proceeding with password binding for existing tenant', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
            } else {
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

            $user->update([
                'tenant_id' => $tenantId
            ]);

            $user->refresh();

            $passwordBindResult = $this->passwordBindingService->bindPasswordWithRetry($user, $plainPassword);

            if (!$passwordBindResult['success']) {
                Log::warning('Password binding failed after all retries during tenant assignment, but tenant was created - will retry later', [
                    'user_id' => $user->id,
                    'error' => $passwordBindResult['error_message'] ?? 'Unknown error'
                ]);
            }

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
                'error_message' => 'System error: ' . $e->getMessage(),
                'swal' => true
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

