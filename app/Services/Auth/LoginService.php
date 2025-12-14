<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DeviceFingerprintService;
use App\Services\SubscriptionService;
use App\Services\AuthRedirectService;

class LoginService
{
    public function __construct(
        private DeviceFingerprintService $deviceFingerprintService,
        private SubscriptionService $subscriptionService,
        private AuthRedirectService $redirectService
    ) {}

    public function handleAuthenticated(Request $request, User $user)
    {
        $this->deviceFingerprintService->recordUserDeviceInfo($user, $request);

        if (!$this->isUserVerified($user)) {
            return [
                'action' => 'logout_and_redirect',
                'route' => 'verification.notice',
                'error' => 'Please verify your email before logging in.'
            ];
        }

        if ($user->hasRole('Sub Admin') && !$user->canSubAdminLogin()) {
            return [
                'action' => 'logout_and_redirect',
                'route' => 'admin-login',
                'error' => 'Your account is not active. Please contact support to activate your account.'
            ];
        }

        if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return [
                'action' => 'redirect',
                'response' => $this->redirectService->getRedirectForUser($user, 'Login successful!')
            ];
        }

        if ($user->hasRole('User')) {
            $this->ensureDefaultFreePlan($user);
            return [
                'action' => 'redirect',
                'response' => $this->redirectService->getRedirectForUser($user, 'Login successful!')
            ];
        }

        return [
            'action' => 'redirect',
            'response' => redirect()->route('user.profile')->with('success', 'Login successful!')
        ];
    }

    public function checkEmailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function shouldRedirectToAdminLogin(string $email): bool
    {
        $user = User::where('email', $email)->first();
        return $user && $user->hasAnyRole(['Super Admin', 'Sub Admin']);
    }

    public function handleCustomLogin(Request $request, array $credentials): array
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'email',
                'message' => 'User does not exist.'
            ];
        }

        if (!$user->hasAnyRole(['Super Admin', 'Sub Admin']) && !$this->isUserVerified($user)) {
            return [
                'success' => false,
                'action' => 'redirect',
                'route' => 'verification.notice',
                'error' => 'Please verify your email before logging in.'
            ];
        }

        return [
            'success' => true,
            'user' => $user
        ];
    }

    private function isUserVerified(User $user): bool
    {
        return ($user->status == 1) && !is_null($user->email_verified_at);
    }

    private function ensureDefaultFreePlan(User $user): void
    {
        try {
            if (!$user->hasRole('User')) {
                return;
            }

            $user->refresh();
            $user->load('package', 'userLicence');

            if (!$user->tenant_id) {
                return;
            }

            $activeLicense = $user->userLicence;
            if ($activeLicense && $activeLicense->isActive() && !$activeLicense->isExpired()) {
                return;
            }

            $hasPaidPackageOrder = $user->orders()
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->whereHas('package', function ($query) {
                    $query->whereRaw('LOWER(name) != ?', ['free']);
                })
                ->exists();

            if ($hasPaidPackageOrder) {
                Log::info('Skipping Free package assignment - user has purchased paid packages', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            if ($user->package && strtolower($user->package->name) !== 'free') {
                Log::info('Skipping Free package assignment - user has paid package assigned', [
                    'user_id' => $user->id,
                    'package_name' => $user->package->name,
                ]);
                return;
            }

            $freePackage = \App\Models\Package::where(function ($query) {
                $query->where('price', 0)
                    ->orWhereRaw('LOWER(name) = ?', ['free']);
            })->first();

            if (!$freePackage) {
                Log::warning('Free package not found when ensuring default plan on login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            $result = $this->subscriptionService->assignFreePlanImmediately($user, $freePackage);

            Log::info('Default free plan ensured on login', [
                'user_id' => $user->id,
                'package_id' => $freePackage->id,
                'license_id' => $result['license_id'] ?? null,
                'order_id' => $result['order_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to ensure default free plan on login', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
