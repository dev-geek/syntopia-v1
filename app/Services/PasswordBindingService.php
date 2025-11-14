<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasswordBindingService
{
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

    public function bindPassword(User $user, string $plainPassword): array
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
            // Call password binding API
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
                'data' => $bindJson['data'] ?? null,
                'error_message' => null
            ];

        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API for password binding', [
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

    /**
     * Create tenant and bind password - returns tenant_id if successful
     */
    public function createTenantAndBindPassword(User $user, string $plainPassword): array
    {
        if (!$this->validatePasswordFormat($plainPassword)) {
            Log::error('Password format validation failed before tenant creation', [
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

                Log::error('Failed to bind password after tenant creation', [
                    'user_id' => $user->id,
                    'response' => $bindJson
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error_message' => $errorMessage
                ];
            }

            Log::info('Tenant created and password bound successfully', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);

            return [
                'success' => true,
                'data' => [
                    'tenantId' => $tenantId
                ],
                'error_message' => null
            ];

        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API for tenant creation and password binding', [
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

        Log::error('Failed to create tenant', [
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
}
