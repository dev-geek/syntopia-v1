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

        $response = Http::withHeaders($this->getHeaders())
            ->post(self::API_BASE_URL . '/api/partner/channel/inventory/subscription/summary/search', $payload);

        if (!$response->successful() || $response->json('code') !== 200) {
            Log::error('Failed to fetch subscription summary', [
                'response' => $response->body(),
                'tenant_id' => $tenantId
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

        $response = Http::withHeaders($this->getHeaders())
            ->post(self::API_BASE_URL . '/api/partner/tenant/subscription/license/add', $payload);

        $responseData = $response->json();
        Log::info("License response", $responseData);

        return $response->successful() && $responseData['code'] === 200;
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
