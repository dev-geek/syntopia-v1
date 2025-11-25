<?php

namespace App\Services;

use App\Models\User;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Order;

class LicenseService
{
    private $licenseApiService;

    public function __construct(LicenseApiService $licenseApiService)
    {
        $this->licenseApiService = $licenseApiService;
    }

    /**
     * Create a new license for a user and activate it
     */
    public function createAndActivateLicense(User $user, Package $package, string $subscriptionId = null, string $paymentGateway = null, bool $isUpgradeAttempt = false): ?UserLicence
    {
        try {
            DB::beginTransaction();

            // Check if user has tenant_id
            if (!$user->tenant_id) {
                Log::error('User does not have tenant_id', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                DB::rollBack();
                return null;
            }

            // Get license keys from external API
            $summaryData = null;
            try {
                $summaryData = $this->licenseApiService->getSubscriptionSummary($user->tenant_id, true);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('LicenseService: cURL connection error when getting subscription summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'error' => $e->getMessage()
                ]);
                DB::rollBack();
                throw new \Exception('Failed to connect to the license server to get subscription details. Please check your internet connection and try again.');
            } catch (\Exception $e) {
                Log::error('LicenseService: Unexpected error when getting subscription summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'error' => $e->getMessage()
                ]);
                // Fall through to general error handling if empty
            }

            if (empty($summaryData)) {
                Log::error('Failed to get license summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                DB::rollBack();
                return null;
            }

            $createdLicenses = [];

            $payproglobalGateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
            $fastspringGateway = PaymentGateways::where('name', 'FastSpring')->first();
            $paddleGateway = PaymentGateways::where('name', 'Paddle')->first();

            $isPayProGlobal = $paymentGateway == ($payproglobalGateway ? $payproglobalGateway->id : null);
            $isFastSpring = $paymentGateway == ($fastspringGateway ? $fastspringGateway->id : null);
            $isPaddle = $paymentGateway == ($paddleGateway ? $paddleGateway->id : null);

            // If subscription_id is not provided, try to get it from the user's latest completed order's transaction_id
            if (!$subscriptionId) {
                $latestOrder = Order::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('package_id', $package->id)
                    ->latest('created_at')
                    ->first();

                if ($latestOrder && $latestOrder->transaction_id) {
                    $subscriptionId = $latestOrder->transaction_id;
                    Log::info('Using transaction_id from order as subscription_id', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'order_id' => $latestOrder->id,
                        'transaction_id' => $subscriptionId
                    ]);
                }
            }

            if (!$subscriptionId && !$isPayProGlobal && !$isFastSpring && !$isPaddle) {
                Log::info('No subscription_id provided, skipping license record creation', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'payment_gateway' => $paymentGateway
                    ]);

                if (!empty($summaryData)) {
                    $firstLicenseKey = $summaryData[0]['subscriptionCode'] ?? null;
                    if ($firstLicenseKey) {
                        Log::info('License key available but no record created (no subscription_id)', [
                            'user_id' => $user->id,
                            'license_key' => $firstLicenseKey
                        ]);
                    }
                }

                DB::commit();
                return null;
            }

            if (!$subscriptionId && $isPayProGlobal) {
                $subscriptionId = 'PPG-ORDER-' . time() . '-' . $user->id;
                Log::info('Generated subscription_id for PayProGlobal', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'generated_subscription_id' => $subscriptionId
                ]);
            }

            if (!$subscriptionId && $isFastSpring) {
                $subscriptionId = 'FS-ORDER-' . time() . '-' . $user->id;
                Log::info('Generated subscription_id for FastSpring', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'generated_subscription_id' => $subscriptionId
                ]);
            }

            if (!$subscriptionId && $isPaddle) {
                $subscriptionId = 'PADDLE-ORDER-' . time() . '-' . $user->id;
                Log::info('Generated subscription_id for Paddle', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'generated_subscription_id' => $subscriptionId
                ]);
            }


            $planNameToResolve = $package->isFree() ? 'Trial' : $package->name;
            $resolved = $this->licenseApiService->resolvePlanLicense($user->tenant_id, $planNameToResolve, true);
            if (!$resolved) {
                $availableNames = array_map(function ($i) {
                    $n = (string)($i['subscriptionName'] ?? '');
                    $translations = [
                        '试用版' => 'Trial Version',
                        '云端高级直播-一年版' => 'Cloud Advanced Live Streaming – 1 Year Plan',
                    ];
                    $translated = $translations[$n] ?? null;
                    return $translated ? $n . ' (' . $translated . ')' : $n;
                }, $summaryData);

                Log::error('Requested plan not found in API inventory; refusing to assign mismatched license', [
                    'user_id' => $user->id,
                    'requested_plan' => $package->name,
                    'resolved_plan_name' => $planNameToResolve,
                    'available_subscription_names' => $availableNames,
                ]);
                DB::rollBack();
                return null;
            }
            $targetList = [$resolved];

            foreach ($targetList as $licenseData) {
                $licenseKey = $licenseData['subscriptionCode'] ?? null;
                if (!$licenseKey) {
                    continue;
                }

                $licenseApiSuccess = false;
                try {
                    $licenseApiSuccess = $this->licenseApiService->addLicenseToTenant($user->tenant_id, $licenseKey);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::error('LicenseService: cURL connection error when adding license to external API', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'license_key' => $licenseKey,
                        'error' => $e->getMessage()
                    ]);
                    DB::rollBack();
                    throw new \Exception('Failed to connect to the license server. Please check your internet connection and try again.');
                } catch (\Exception $e) {
                    Log::error('LicenseService: Unexpected error when calling addLicenseToTenant', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'license_key' => $licenseKey,
                        'error' => $e->getMessage()
                    ]);

                }

                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'license_key' => $licenseKey
                    ]);
                    continue;
                }

                $expiresAt = $package->isFree()
                    ? null
                    : now()->addMonth();

                $license = UserLicence::create([
                    'user_id' => $user->id,
                    'license_key' => $licenseKey,
                    'package_id' => $package->id,
                    'subscription_id' => $subscriptionId,
                    'payment_gateway_id' => $paymentGateway,
                    'activated_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'is_upgrade_license' => $isUpgradeAttempt,
                    'metadata' => [
                        'created_via' => 'payment',
                        'package_name' => $package->name,
                        'original_license_data' => $licenseData,
                        'expiration_calculated' => true
                    ]
                ]);

                $createdLicenses[] = $license;
            }

            if (empty($createdLicenses)) {
                Log::error('No licenses were created successfully', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                DB::rollBack();
                return null;
            }

            $firstLicense = $createdLicenses[0];
            $user->update([
                'user_license_id' => $firstLicense->id
            ]);

            DB::commit();

            Log::info('Licenses created and activated successfully', [
                'user_id' => $user->id,
                'licenses_created' => count($createdLicenses),
                'package_name' => $package->name,
                'license_keys' => array_map(fn($l) => $l->license_key, $createdLicenses),
                'user_license_id' => $firstLicense->id,
                'expires_at' => $firstLicense->expires_at ? $firstLicense->expires_at->format('Y-m-d H:i:s') : 'Never (Free package)'
            ]);

            return $firstLicense;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create and activate license', [
                'user_id' => $user->id,
                'package_name' => $package->name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the active license for a user
     */
    public function getActiveLicense(User $user): ?UserLicence
    {
        return UserLicence::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['package'])
            ->first();
    }

    /**
     * Get all licenses for a user
     */
    public function getUserLicenses(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return UserLicence::where('user_id', $user->id)
            ->with(['package'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Activate a specific license for a user
     */
    public function activateLicense(UserLicence $license): bool
    {
        try {
            $license->activate();

            // Update user's user_license_id for backward compatibility
            $license->user->update([
                'user_license_id' => $license->id
            ]);

            Log::info('License activated successfully', [
                'license_id' => $license->id,
                'user_id' => $license->user_id,
                'package_name' => $license->package->name
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to activate license', [
                'license_id' => $license->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has an active license for a specific package
     */
    public function hasActiveLicenseForPackage(User $user, string $packageName): bool
    {
        $activeLicense = $this->getActiveLicense($user);
        return $activeLicense && strtolower($activeLicense->package->name) === strtolower($packageName);
    }

    /**
     * Get the newest license for a user (most recently created)
     */
    public function getNewestLicense(User $user): ?UserLicence
    {
        return UserLicence::where('user_id', $user->id)
            ->with(['package'])
            ->latest('created_at')
            ->first();
    }

    /**
     * Deactivate all licenses for a user
     */
    public function deactivateAllLicenses(User $user): void
    {
        UserLicence::where('user_id', $user->id)->update(['is_active' => false]);

        // Clear user's user_license_id for backward compatibility
        $user->update([
            'user_license_id' => null
        ]);

        Log::info('All licenses deactivated for user', ['user_id' => $user->id]);
    }

    /**
     * Check if a user is currently restricted from changing their plan.
     *
     * @param User $user
     * @return bool
     */
    public function canUserChangePlan(User $user): bool
    {
        $activeLicense = $this->getActiveLicense($user);

        // Block changes if there is a pending or scheduled downgrade
        $hasPendingOrScheduledDowngrade = Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'pending_downgrade', 'scheduled_downgrade'])
            ->exists();

        if ($hasPendingOrScheduledDowngrade) {
            return false;
        }

        // If no active license or current license is expired, user can change plan.
        if (!$activeLicense || ($activeLicense->expires_at && $activeLicense->expires_at->isPast())) {
            return true;
        }

        // If an active license exists and it's an upgrade license, prevent further changes.
        if ($activeLicense->is_upgrade_license && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            return false;
        }

        // If an active license exists but it's NOT an upgrade license, allow one upgrade.
        if (!$activeLicense->is_upgrade_license && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            return true; // This allows the first upgrade to a new plan
        }

        return true; // Default to allowing changes if none of the above conditions are met (should not be reached in most cases)
    }
}
