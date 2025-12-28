<?php

namespace App\Services\Auth\Verification;

use App\Models\User;
use App\Services\TenantAssignmentService;
use App\Services\PasswordBindingService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    public function __construct(
        private TenantAssignmentService $tenantAssignmentService,
        private PasswordBindingService $passwordBindingService,
        private SubscriptionService $subscriptionService
    ) {}

    public function verifyCode(User $user, string $verificationCode): array
    {
        if (!$this->isValidVerificationCode($user, $verificationCode)) {
            return [
                'success' => false,
                'error' => 'Invalid verification code.'
            ];
        }

        if (!$user->subscriber_password) {
            Log::error('[VerificationService] No subscriber_password found for user during verification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return [
                'success' => false,
                'error' => 'Password data missing. Please contact support.'
            ];
        }

        try {
            DB::beginTransaction();

            if ($user->tenant_id) {
                return $this->handleUserWithTenant($user);
            }

            $result = $this->handleUserWithoutTenant($user);
            DB::commit();
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VerificationService] Exception during verification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            throw $e;
        }
    }

    private function isValidVerificationCode(User $user, string $verificationCode): bool
    {
        if ($user->verification_code !== $verificationCode) {
            Log::error('[VerificationService] Invalid verification code', [
                'user_id' => $user->id,
                'expected' => $user->verification_code,
                'provided' => $verificationCode
            ]);
            return false;
        }
        return true;
    }

    private function handleUserWithTenant(User $user): array
    {
        Log::info('[VerificationService] User already has tenant_id, ensuring password is bound', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id
        ]);

        if ($user->subscriber_password) {
            $passwordBindResult = $this->passwordBindingService->bindPasswordWithRetry($user, $user->subscriber_password);

            if (!$passwordBindResult['success']) {
                Log::warning('[VerificationService] Password binding failed for user with existing tenant_id - will retry later', [
                    'user_id' => $user->id,
                    'error' => $passwordBindResult['error_message'] ?? 'Unknown error'
                ]);
            } else {
                Log::info('[VerificationService] Password bound successfully for user with existing tenant_id', [
                    'user_id' => $user->id
                ]);
            }
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => 1,
            'verification_code' => null,
        ]);

        DB::commit();

        return [
            'success' => true,
            'user' => $user->fresh()
        ];
    }

    private function handleUserWithoutTenant(User $user): array
    {
        Log::info('[VerificationService] Calling TenantAssignmentService with retry logic', ['user_id' => $user->id]);

        $apiResponse = $this->tenantAssignmentService->assignTenantWithRetry($user);

        if (isset($apiResponse['swal']) && $apiResponse['swal'] === true) {
            if (str_contains($apiResponse['error_message'] ?? '', 'already registered')) {
                $existingUserWithTenant = User::where('email', $user->email)
                    ->whereNotNull('tenant_id')
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUserWithTenant) {
                    Log::warning('[VerificationService] Duplicate user detected with existing tenant', [
                        'current_user_id' => $user->id,
                        'existing_user_id' => $existingUserWithTenant->id,
                        'tenant_id' => $existingUserWithTenant->tenant_id
                    ]);

                    $user->delete();
                    return [
                        'success' => false,
                        'error' => 'An account with this email already exists. Please login instead.',
                        'redirect' => 'login'
                    ];
                }
            }

            $user->update([
                'email_verified_at' => now(),
                'status' => 1,
                'verification_code' => null,
            ]);

            return [
                'success' => false,
                'error' => $apiResponse['error_message'],
                'swal' => true,
                'user' => $user->fresh()
            ];
        }

        if (!$apiResponse['success'] || empty($apiResponse['data']['tenantId'])) {
            Log::error('[VerificationService] Tenant assignment failed after all retries', [
                'user_id' => $user->id,
                'apiResponse' => $apiResponse
            ]);

            $user->update([
                'email_verified_at' => now(),
                'status' => 1,
                'verification_code' => null,
            ]);

            $errorMsg = $apiResponse['error_message'] ?? 'System API is down right now. Please try again later.';
            return [
                'success' => false,
                'error' => $errorMsg . ' Your account has been created and tenant assignment will be retried automatically.',
                'redirect' => 'login',
                'user' => $user->fresh()
            ];
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => 1,
            'verification_code' => null,
        ]);

        $user->refresh();
        // Only bind password if it wasn't already bound during tenant assignment
        $passwordAlreadyBound = $apiResponse['password_bound'] ?? false;
        
        Log::info('[VerificationService] Checking password binding status', [
            'user_id' => $user->id,
            'has_subscriber_password' => !empty($user->subscriber_password),
            'password_bound_during_tenant_assignment' => $passwordAlreadyBound
        ]);
        
        if ($user->subscriber_password && !$passwordAlreadyBound) {
            $passwordBindResult = $this->passwordBindingService->bindPasswordWithRetry($user, $user->subscriber_password);

            if (!$passwordBindResult['success']) {
                Log::warning('[VerificationService] Password binding failed after tenant assignment - will retry later', [
                    'user_id' => $user->id,
                    'error' => $passwordBindResult['error_message'] ?? 'Unknown error'
                ]);
            } else {
                Log::info('[VerificationService] Password bound successfully after tenant assignment', [
                    'user_id' => $user->id
                ]);
            }
        } elseif ($user->subscriber_password && $passwordAlreadyBound) {
            Log::info('[VerificationService] Password already bound during tenant assignment, skipping duplicate binding', [
                'user_id' => $user->id
            ]);
        }

        $this->assignFreePackageIfNeeded($user);

        return [
            'success' => true,
            'user' => $user->fresh()
        ];
    }

    private function assignFreePackageIfNeeded(User $user): void
    {
        $user->refresh();
        $user->load('package', 'userLicence', 'orders');

        $hasPaidPackageOrder = $user->orders()
            ->where('status', 'completed')
            ->where('amount', '>', 0)
            ->whereHas('package', function ($query) {
                $query->whereRaw('LOWER(name) != ?', ['free']);
            })
            ->exists();

        if (!$hasPaidPackageOrder) {
            $hasPaidPackage = $user->package && strtolower($user->package->name) !== 'free';

            if (!$hasPaidPackage) {
                $freePackage = \App\Models\Package::where(function ($query) {
                    $query->where('price', 0)
                        ->orWhereRaw('LOWER(name) = ?', ['free']);
                })->first();

                if (!$freePackage) {
                    Log::error('[VerificationService] Free package not found during verification', [
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                    ]);
                    throw new \Exception('Free package is not configured. Please contact support.');
                }

                $this->subscriptionService->assignFreePlanImmediately($user, $freePackage);
                Log::info('[VerificationService] Free package assigned during email verification', ['user_id' => $user->id]);
            } else {
                Log::info('[VerificationService] Skipping Free package assignment - user has paid package', [
                    'user_id' => $user->id,
                    'package_name' => $user->package->name,
                ]);
            }
        } else {
            Log::info('[VerificationService] Skipping Free package assignment - user has purchased paid packages', [
                'user_id' => $user->id,
            ]);
        }
    }
}
