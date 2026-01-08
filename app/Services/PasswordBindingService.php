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
                'error_message' => 'Password format is invalid. Please contact support.',
                'swal' => true
            ];
        }

        if (!$user->tenant_id) {
            Log::warning('Cannot bind password - user missing tenant_id', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return [
                'success' => false,
                'data' => null,
                'error_message' => 'Cannot bind password without tenant_id. Tenant assignment will be retried automatically.',
                'swal' => false
            ];
        }

        try {
            $passwordBindResponse = $this->makeXiaoiceApiRequest(
                'api/partner/tenant/user/password/bind',
                [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]
            );

            $bindJson = $passwordBindResponse->json();

            $isSuccess = $passwordBindResponse->successful() &&
                        (isset($bindJson['code']) && in_array($bindJson['code'], [200, 201]));

            if (!$isSuccess) {
                $errorMessage = $bindJson['message'] ?? '';
                $errorCode = $bindJson['code'] ?? null;

                if (str_contains(strtolower($errorMessage), 'already') ||
                    str_contains(strtolower($errorMessage), 'bound') ||
                    $errorCode == 200) {
                    return [
                        'success' => true,
                        'data' => $bindJson['data'] ?? null,
                        'error_message' => null
                    ];
                }

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

            // Success is logged in bindPasswordWithRetry with attempt info
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
                'error_message' => 'System error: ' . $e->getMessage(),
                'swal' => true
            ];
        }
    }

    public function bindPasswordWithRetry(User $user, string $plainPassword, int $maxAttempts = 3): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $result = $this->bindPassword($user, $plainPassword);

            if ($result['success']) {
                return $result;
            }

            $lastError = $result;

            // Wait before retrying (exponential backoff)
            if ($attempt < $maxAttempts) {
                $delay = $attempt * 500; // 500ms, 1000ms, 1500ms
                usleep($delay * 1000);
            }
        }

        Log::warning('Password binding failed after all retries', [
            'user_id' => $user->id,
            'attempts' => $attempt,
            'error' => $lastError['error_message'] ?? 'Unknown error'
        ]);

        return $lastError ?? [
            'success' => false,
            'data' => null,
            'error_message' => 'Password binding failed after all retry attempts.',
            'swal' => false
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
