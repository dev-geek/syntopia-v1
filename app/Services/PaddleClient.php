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
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->patch("{$apiBaseUrl}/subscriptions/{$subscriptionId}", [
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
        $environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => 'immediately'
        ]);
    }
}

