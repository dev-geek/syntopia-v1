<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaddleClient
{
    private $apiKey;
    private $environment;
    private $apiBaseUrl;

    public function __construct()
    {
        $this->apiKey = config('payment.gateways.Paddle.api_key');
        $this->environment = config('payment.gateways.Paddle.environment', 'sandbox');
        $this->apiBaseUrl = $this->environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';
    }

    public function upgradeSubscription(string $subscriptionId, string $newPriceId, string $prorationBillingMode = null)
    {
        $payload = [
            'items' => [['price_id' => $newPriceId, 'quantity' => 1]]
        ];

        if ($prorationBillingMode) {
            $payload['proration_billing_mode'] = $prorationBillingMode;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->patch("{$this->apiBaseUrl}/subscriptions/{$subscriptionId}", $payload);

        if (!$response->successful()) {
            Log::error('Paddle subscription upgrade failed', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newPriceId, string $prorationBillingMode = null)
    {
        // For downgrades, we'll use the same logic as upgrades but with a different log message
        $payload = [
            'items' => [['price_id' => $newPriceId, 'quantity' => 1]]
        ];

        if ($prorationBillingMode) {
            $payload['proration_billing_mode'] = $prorationBillingMode;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->patch("{$this->apiBaseUrl}/subscriptions/{$subscriptionId}", $payload);

        if (!$response->successful()) {
            Log::error('Paddle subscription downgrade failed', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId, int $billingPeriod = 1)
    {
        $effectiveFrom = $billingPeriod === 0 ? 'immediately' : 'next_billing_period';

        Log::info('Canceling Paddle subscription', [
            'subscription_id' => $subscriptionId,
            'effective_from' => $effectiveFrom,
            'billing_period' => $billingPeriod
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$this->apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => $effectiveFrom
        ]);

        if (!$response->successful()) {
            Log::error('Paddle subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'effective_from' => $effectiveFrom,
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);
            return null;
        }

        $responseData = $response->json();

        Log::info('Paddle subscription cancellation successful', [
            'subscription_id' => $subscriptionId,
            'effective_from' => $effectiveFrom,
            'response_data' => $responseData
        ]);

        return $responseData;
    }

    public function getSubscription(string $subscriptionId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$this->apiBaseUrl}/subscriptions/{$subscriptionId}");

        if (!$response->successful()) {
            Log::error('Failed to get Paddle subscription', [
                'subscription_id' => $subscriptionId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json()['data'] ?? null;
    }

    public function getTransaction(string $transactionId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$this->apiBaseUrl}/transactions/{$transactionId}");

        if (!$response->successful()) {
            Log::error('Failed to get Paddle transaction', [
                'transaction_id' => $transactionId,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json()['data'] ?? null;
    }

    public function getProducts()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$this->apiBaseUrl}/products", ['include' => 'prices']);

        if (!$response->successful()) {
            Log::error('Failed to get Paddle products', [
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json()['data'] ?? [];
    }

    public function findProductByName(string $productName)
    {
        $products = $this->getProducts();

        if (!$products) {
            return null;
        }

        return collect($products)->firstWhere('name', $productName);
    }

    public function findActivePriceForProduct(string $productId)
    {
        $products = $this->getProducts();

        if (!$products) {
            return null;
        }

        $product = collect($products)->firstWhere('id', $productId);

        if (!$product) {
            return null;
        }

        return collect($product['prices'])->firstWhere('status', 'active');
    }
}

