<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaddleClient
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
        ])->patch("https://api.paddle.com/subscriptions/{$subscriptionId}", [
            'items' => [
                [
                    'price_id' => $newProductId,
                    'quantity' => 1
                ]
            ],
            'proration_billing_mode' => 'prorated_immediately'
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
        ])->post("https://api.paddle.com/subscriptions/{$subscriptionId}/cancel");
    }
}

