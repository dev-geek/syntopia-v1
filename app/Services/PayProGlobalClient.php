<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayProGlobalClient
{
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function upgradeSubscription(string $subscriptionId, string $newProductId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.payproglobal.com/v1/subscriptions/upgrade', [
            'subscription_id' => $subscriptionId,
            'product_id' => $newProductId,
            'prorate' => true
        ]);

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newProductId)
    {
        return $this->upgradeSubscription($subscriptionId, $newProductId);
    }

    public function cancelSubscription(string $subscriptionId)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey
        ])->post('https://api.payproglobal.com/v1/subscriptions/cancel', [
            'subscription_id' => $subscriptionId
        ]);
    }
}
