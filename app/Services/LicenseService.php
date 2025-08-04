<?php

namespace App\Services;

use App\Models\User;
use App\Models\Package;
use App\Models\UserLicence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    public function createAndActivateLicense(User $user, Package $package, string $subscriptionId = null, string $paymentGateway = null): ?UserLicence
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
            $summaryData = $this->licenseApiService->getSubscriptionSummary($user->tenant_id, true);
            if (empty($summaryData)) {
                Log::error('Failed to get license summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                DB::rollBack();
                return null;
            }

            $createdLicenses = [];

            // Only create license records if there's a subscription_id
            if (!$subscriptionId) {
                Log::info('No subscription_id provided, skipping license record creation', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);

                // No license record created (no subscription_id provided)
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

            // Create a license for each available license key
            foreach ($summaryData as $licenseData) {
                $licenseKey = $licenseData['subscriptionCode'] ?? null;
                if (!$licenseKey) {
                    continue;
                }

                // Add license to external API
                $licenseApiSuccess = $this->licenseApiService->addLicenseToTenant($user->tenant_id, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'license_key' => $licenseKey
                    ]);
                    continue;
                }

                // Calculate expiration date based on package type
                // Free packages have no expiration, all others expire in 1 month
                $expiresAt = strtolower($package->name) === 'free'
                    ? null
                    : now()->addMonth();

                // Create the license record
                $license = UserLicence::create([
                    'user_id' => $user->id,
                    'license_key' => $licenseKey,
                    'package_id' => $package->id,
                    'subscription_id' => $subscriptionId,
                    'payment_gateway_id' => $paymentGateway,
                    'activated_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
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
}
