<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LicenseApiService
{
    private const API_BASE_URL = 'https://openapi.xiaoice.com/vh-cp';
    private const SUBSCRIPTION_KEY = '5c745ccd024140ffad8af2ed7a30ccad';
    private const CACHE_TTL = 300; // 5 minutes

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
                ->timeout(30) // Set a timeout for the request
                ->post(self::API_BASE_URL . '/api/partner/channel/inventory/subscription/summary/search', $payload);

                // output: {
                //     "code": 200,
                //     "message": "成功",
                //     "traceId": "a76d893ddc5ce6eb",
                //     "data": {
                //         "data": [
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "4db87c12de824ff094687d8020d1a804",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-FREE-02",
                //                 "subscriptionName": "试用版",
                //                 "total": 10,
                //                 "used": 10,
                //                 "remaining": 0
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "50c1a4b18b7447dab0d06e2e314bc7e0",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-STD-03",
                //                 "subscriptionName": "云端高级直播-一年版",
                //                 "total": 1,
                //                 "used": 1,
                //                 "remaining": 0
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "0f4f9cced9bd46ed92bc62fb90fa375d",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-OVS-01",
                //                 "subscriptionName": "Free Plan",
                //                 "total": 150,
                //                 "used": 150,
                //                 "remaining": 0
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "5aa5b677e6d34e4b94f4e20b1e08038e",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-OVS-04",
                //                 "subscriptionName": "Business Plan",
                //                 "total": 14,
                //                 "used": 14,
                //                 "remaining": 0
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "7c05a9a7cba64830a551d0549d983a71",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-GLB-01",
                //                 "subscriptionName": "Trial",
                //                 "total": 50,
                //                 "used": 31,
                //                 "remaining": 19
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "f5d7f58bcbc64696be2ba2c0ccb1bd83",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-OVS-02",
                //                 "subscriptionName": "Starter Plan",
                //                 "total": 10,
                //                 "used": 10,
                //                 "remaining": 0
                //             },
                //             {
                //                 "channelId": "a581486eb1094846b92ac028de2264c6",
                //                 "appId": 1,
                //                 "subscriptionId": "c2fa257a54f84a138c3f961602611894",
                //                 "subscriptionType": "license",
                //                 "subscriptionCode": "PKG-CL-OVS-03",
                //                 "subscriptionName": "Pro Plan",
                //                 "total": 10,
                //                 "used": 10,
                //                 "remaining": 0
                //             }
                //         ],
                //         "total": 7,
                //         "pageIndex": 1,
                //         "pageSize": 100
                //     }
                // }
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
            return null; // Indicate failure due to connection error
        } catch (\Exception $e) {
            Log::error('Unexpected error when fetching subscription summary', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }

        $allLicenses = $response->json('data.data') ?? [];

        // Filter out licenses with 0 remaining capacity
        $availableLicenses = array_filter($allLicenses, function ($license) {
            return ($license['remaining'] ?? 0) > 0;
        });

        Log::info('License availability filtered', [
            'total_licenses' => count($allLicenses),
            'available_licenses' => count($availableLicenses),
            'tenant_id' => $tenantId
        ]);

        return array_values($availableLicenses); // Reset array keys
    }

    public function addLicenseToTenant(string $tenantId, string $licenseKey): bool
    {
        $payload = [
            'tenantId' => $tenantId,
            'subscriptionCode' => $licenseKey,
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30) // Set a timeout for the request
                ->post(self::API_BASE_URL . '/api/partner/tenant/subscription/license/add', $payload);

            $responseData = $response->json();
            Log::info("License response", $responseData);

            if ($response->successful() && $responseData['code'] === 200) {
                return true;
            } else {
                Log::error('Failed to add license to external API', [
                    'tenant_id' => $tenantId,
                    'license_key' => $licenseKey,
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('cURL connection error when adding license to external API', [
                'tenant_id' => $tenantId,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
            return false; // Indicate failure due to connection error
        } catch (\Exception $e) {
            Log::error('Unexpected error when adding license to external API', [
                'tenant_id' => $tenantId,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Resolve a subscription entry from API summary by human plan name.
     * Accepts English and Chinese aliases and normalizes common variants.
     * Returns the first item with remaining > 0 that matches; otherwise null.
     */
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
        // remove common suffixes
        $p = str_replace([' plan', '-plan'], '', $p);
        return $p;
    }

    private function planMatches(string $normalizedPlan, string $subscriptionName, string $subscriptionCode): bool
    {
        $name = mb_strtolower($subscriptionName);

        // Chinese -> English mappings for clarity
        // 试用版 -> Trial Version
        // 云端高级直播-一年版 -> Cloud Advanced Live Streaming – 1 Year Plan

        $aliases = [
            'free' => ['free', 'free version', 'free plan', '免费', '免费版'],
            'trial' => ['trial', 'trial version', '试用', '试用版'],
            'starter' => ['starter', 'starter plan'],
            'pro' => ['pro', 'pro plan'],
            'business' => ['business', 'business plan'],
            'enterprise' => ['enterprise', 'enterprise plan', '企业版'],
            'avatar customization' => ['avatar customization', 'avatar-customization', 'avatar', 'avatar 定制', '形象定制'],
            'voice customization' => ['voice customization', 'voice-customization', 'voice', '语音定制'],
            'cloud-advanced-live-streaming-1-year' => ['云端高级直播-一年版', 'cloud advanced live streaming – 1 year plan', 'cloud advanced live streaming-1 year plan'],
        ];

        // infer key from normalizedPlan
        $key = $normalizedPlan;
        if (!isset($aliases[$key])) {
            // map common variants
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

        // direct name match
        if (isset($aliases[$key])) {
            foreach ($aliases[$key] as $alias) {
                if (mb_strpos($name, mb_strtolower($alias)) !== false) {
                    return true;
                }
            }
        }

        // heuristic by code prefixes
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
}
