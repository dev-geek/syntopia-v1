<?php

namespace App\Services\License;

use App\Models\User;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LicenseApiService
{
    private const API_BASE_URL = 'https://openapi.xiaoice.com/vh-cp';
    private const SUBSCRIPTION_KEY = '5c745ccd024140ffad8af2ed7a30ccad';
    private const CACHE_TTL = 300;

    private function getHeaders(): array
    {
        return [
            'subscription-key' => self::SUBSCRIPTION_KEY,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    public function getSubscriptionSummary(?string $tenantId = null, bool $bypassCache = false): ?array
    {
        $cacheKey = $this->getCacheKey('subscription_summary', $tenantId);

        if ($bypassCache) {
            Log::info('Making fresh license API call (bypassing cache)', [
                'tenant_id' => $tenantId
            ]);
            return $this->fetchSubscriptionSummary($tenantId);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            return $this->fetchSubscriptionSummary($tenantId);
        });
    }

    private function fetchSubscriptionSummary(?string $tenantId = null): ?array
    {
        $payload = [
            'pageIndex' => 1,
            'pageSize' => 100,
            'appIds' => [1],
            'subscriptionType' => 'license',
        ];

        if ($tenantId) {
            $payload['tenantId'] = $tenantId;
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post(self::API_BASE_URL . '/api/partner/channel/inventory/subscription/summary/search', $payload);

            if (!$response->successful() || $response->json('code') !== 200) {
                Log::error('Failed to fetch subscription summary', [
                    'response' => $response->body(),
                    'tenant_id' => $tenantId
                ]);
                return null;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('cURL connection error when fetching subscription summary', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error when fetching subscription summary', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }

        $allLicenses = $response->json('data.data') ?? [];

        $availableLicenses = array_filter($allLicenses, function ($license) {
            return ($license['remaining'] ?? 0) > 0;
        });

        Log::info('License availability filtered', [
            'total_licenses' => count($allLicenses),
            'available_licenses' => count($availableLicenses),
            'tenant_id' => $tenantId
        ]);

        return array_values($availableLicenses);
    }

    public function addLicenseToTenant(string $tenantId, string $licenseKey): bool
    {
        $payload = [
            'tenantId' => $tenantId,
            'subscriptionCode' => $licenseKey,
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post(self::API_BASE_URL . '/api/partner/tenant/subscription/license/add', $payload);

            $responseData = $response->json();
            Log::info("License response", $responseData);

            if ($response->successful() && isset($responseData['code']) && $responseData['code'] === 200) {
                return true;
            } else {
                $statusCode = $response->status();
                $apiCode = $responseData['code'] ?? null;
                $apiMessage = $responseData['message'] ?? 'No error message provided';
                $apiData = $responseData['data'] ?? null;

                Log::error('Failed to add license to external API', [
                    'tenant_id' => $tenantId,
                    'license_key' => $licenseKey,
                    'http_status' => $statusCode,
                    'api_code' => $apiCode,
                    'api_message' => $apiMessage,
                    'api_data' => $apiData,
                    'response_body' => $response->body(),
                    'response_json' => $responseData
                ]);

                if ($apiCode === 730 || (is_string($apiMessage) && (str_contains($apiMessage, 'already exists') || str_contains($apiMessage, 'exist')))) {
                    Log::warning('License may already exist for tenant - verifying', [
                        'tenant_id' => $tenantId,
                        'license_key' => $licenseKey,
                        'api_message' => $apiMessage
                    ]);

                    try {
                        $summary = $this->getSubscriptionSummary($tenantId, true);
                        if ($summary) {
                            foreach ($summary as $item) {
                                if (($item['subscriptionCode'] ?? '') === $licenseKey) {
                                    Log::info('License confirmed to exist in tenant subscription - proceeding with license record creation', [
                                        'tenant_id' => $tenantId,
                                        'license_key' => $licenseKey
                                    ]);
                                    return true;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not verify license existence via summary', [
                            'tenant_id' => $tenantId,
                            'license_key' => $licenseKey,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return false;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('cURL connection error when adding license to external API', [
                'tenant_id' => $tenantId,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected error when adding license to external API', [
                'tenant_id' => $tenantId,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function resolvePlanLicense(?string $tenantId, string $planName, bool $bypassCache = false): ?array
    {
        $summaryData = $this->getSubscriptionSummary($tenantId, $bypassCache) ?? [];

        if (empty($summaryData)) {
            return null;
        }

        $normalized = $this->normalizePlanName($planName);

        foreach ($summaryData as $item) {
            $name = (string)($item['subscriptionName'] ?? '');
            $code = (string)($item['subscriptionCode'] ?? '');
            $remaining = (int)($item['remaining'] ?? 0);

            if ($remaining <= 0) {
                continue;
            }

            if ($this->planMatches($normalized, $name, $code)) {
                return $item;
            }
        }

        return null;
    }

    private function normalizePlanName(string $planName): string
    {
        $p = trim(mb_strtolower($planName));
        $p = str_replace([' plan', '-plan'], '', $p);
        return $p;
    }

    private function planMatches(string $normalizedPlan, string $subscriptionName, string $subscriptionCode): bool
    {
        $name = mb_strtolower($subscriptionName);

		$aliases = [
			'free' => ['Free', 'free', 'free version', 'free plan', 'Trial', 'trial', 'trial version'],
			'trial' => ['Trial', 'trial', 'trial version'],
			'starter' => ['Starter','starter', 'starter plan', 'starter package', 'starter tier'],
			'pro' => ['Pro', 'pro', 'pro plan'],
			'business' => ['Business', 'business', 'business plan'],
			'enterprise' => ['Enterprise', 'enterprise', 'enterprise plan'],
			'avatar customization' => ['Avatar Customization (Clone Yourself)', 'avatar customization', 'avatar-customization', 'avatar', 'avatar customization (clone yourself)'],
			'cloud-advanced-live-streaming-1-year' => ['Cloud Advanced Live Streaming – 1 Year Plan', 'cloud advanced live streaming – 1 year plan', 'cloud advanced live streaming-1 year plan'],
		];

        $key = $normalizedPlan;
        if (!isset($aliases[$key])) {
            $map = [
                'trial version' => 'trial',
                'cloud advanced live streaming – 1 year plan' => 'cloud-advanced-live-streaming-1-year',
                'cloud advanced live streaming-1 year plan' => 'cloud-advanced-live-streaming-1-year',
                'avatar-customization' => 'avatar customization',
                'voice-customization' => 'voice customization',
            ];
            if (isset($map[$key])) {
                $key = $map[$key];
            }
        }

        if (isset($aliases[$key])) {
            foreach ($aliases[$key] as $alias) {
                if (mb_strpos($name, mb_strtolower($alias)) !== false) {
                    return true;
                }
            }
        }

		$codeExactMap = [
			'starter' => ['PKG-CL-OVS-02'],
			'pro' => ['PKG-CL-OVS-03'],
			'business' => ['PKG-CL-OVS-04'],
			'trial' => ['PKG-CL-GLB-01'],
		];
		if (isset($codeExactMap[$key]) && in_array($subscriptionCode, $codeExactMap[$key], true)) {
			return true;
		}

		$prefixMap = [
			'free' => ['PKG-CL-FREE'],
			'starter' => ['PKG-CL-OVS-02'],
			'pro' => ['PKG-CL-OVS-03'],
			'business' => ['PKG-CL-OVS-04'],
			'trial' => ['PKG-CL-GLB'],
		];
		if (isset($prefixMap[$key])) {
			foreach ($prefixMap[$key] as $prefix) {
				if (stripos($subscriptionCode, $prefix) === 0) {
					return true;
				}
			}
		}

        return false;
    }

    public function checkLicenseAvailability(): bool
    {
        $summaryData = $this->getSubscriptionSummary();

        if (empty($summaryData)) {
            Log::error('No subscription data found in license availability check');
            return false;
        }

        $licenseKey = $summaryData[0]['subscriptionCode'] ?? null;
        if (!$licenseKey) {
            Log::error('No license codes available in license availability check');
            return false;
        }

        Log::info('License availability check passed', ['available' => true]);
        return true;
    }

    public function makeLicense(?string $tenantId = null, bool $bypassCache = false): ?string
    {
        $summaryData = $this->getSubscriptionSummary($tenantId, $bypassCache);

        if (empty($summaryData)) {
            Log::error('No subscription data found in summary response', [
                'bypass_cache' => $bypassCache,
                'tenant_id' => $tenantId
            ]);
            return null;
        }

        $licenseKey = $summaryData[0]['subscriptionCode'] ?? null;
        if (!$licenseKey) {
            Log::error('Subscription code not found in summary response', [
                'bypass_cache' => $bypassCache,
                'tenant_id' => $tenantId
            ]);
            return null;
        }

        Log::info('License key retrieved', [
            'license_key' => $licenseKey,
            'bypass_cache' => $bypassCache,
            'tenant_id' => $tenantId
        ]);

        return $licenseKey;
    }

    private function getCacheKey(string $prefix, ?string $tenantId = null): string
    {
        return $prefix . '_' . ($tenantId ?? 'general');
    }

    public function createAndActivateLicense(User $user, Package $package, string $subscriptionId = null, string $paymentGateway = null, bool $isUpgradeAttempt = false): ?UserLicence
    {
        try {
            DB::beginTransaction();

            if (!$user->tenant_id) {
                Log::error('User does not have tenant_id', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                DB::rollBack();
                return null;
            }

            $summaryData = null;
            try {
                $summaryData = $this->getSubscriptionSummary($user->tenant_id, true);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('LicenseApiService: cURL connection error when getting subscription summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'error' => $e->getMessage()
                ]);
                DB::rollBack();
                throw new \Exception('Failed to connect to the license server to get subscription details. Please check your internet connection and try again.');
            } catch (\Exception $e) {
                Log::error('LicenseApiService: Unexpected error when getting subscription summary', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'error' => $e->getMessage()
                ]);
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

            $planNameToResolve = $package->name;
            $resolved = $this->resolvePlanLicense($user->tenant_id, $planNameToResolve, true);
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
                    $licenseApiSuccess = $this->addLicenseToTenant($user->tenant_id, $licenseKey);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::error('LicenseApiService: cURL connection error when adding license to external API', [
                        'user_id' => $user->id,
                        'package_name' => $package->name,
                        'license_key' => $licenseKey,
                        'error' => $e->getMessage()
                    ]);
                    DB::rollBack();
                    throw new \Exception('Failed to connect to the license server. Please check your internet connection and try again.');
                } catch (\Exception $e) {
                    Log::error('LicenseApiService: Unexpected error when calling addLicenseToTenant', [
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

    public function getActiveLicense(User $user): ?UserLicence
    {
        return UserLicence::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['package'])
            ->first();
    }

    public function getUserLicenses(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return UserLicence::where('user_id', $user->id)
            ->with(['package'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function activateLicense(UserLicence $license): bool
    {
        try {
            $license->activate();

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

    public function hasActiveLicenseForPackage(User $user, string $packageName): bool
    {
        $activeLicense = $this->getActiveLicense($user);
        return $activeLicense && strtolower($activeLicense->package->name) === strtolower($packageName);
    }

    public function getNewestLicense(User $user): ?UserLicence
    {
        return UserLicence::where('user_id', $user->id)
            ->with(['package'])
            ->latest('created_at')
            ->first();
    }

    public function deactivateAllLicenses(User $user): void
    {
        UserLicence::where('user_id', $user->id)->update(['is_active' => false]);

        $user->update([
            'user_license_id' => null
        ]);

        Log::info('All licenses deactivated for user', ['user_id' => $user->id]);
    }

    public function canUserChangePlan(User $user): bool
    {
        $activeLicense = $this->getActiveLicense($user);

        $hasPendingOrScheduledDowngrade = Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'pending_downgrade', 'scheduled_downgrade'])
            ->exists();

        if ($hasPendingOrScheduledDowngrade) {
            return false;
        }

        if (!$activeLicense || ($activeLicense->expires_at && $activeLicense->expires_at->isPast())) {
            return true;
        }

        if ($activeLicense->is_upgrade_license && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            return false;
        }

        if (!$activeLicense->is_upgrade_license && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            return true;
        }

        return true;
    }
}
