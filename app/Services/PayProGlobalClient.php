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

    public function cancelSubscription(string $subscriptionId, int $cancellationReasonId = null, string $reasonText = null, bool $sendCustomerNotification = false)
    {
        $payload = [
            'subscriptionId' => (int) $subscriptionId,
            'vendorAccountId' => (int) config('payment.gateways.PayProGlobal.vendor_account_id'),
            'apiSecretKey' => config('payment.gateways.PayProGlobal.api_secret_key'),
            'sendCustomerNotification' => $sendCustomerNotification
        ];

        // Add cancellation reason - either predefined ID or custom text
        if ($cancellationReasonId) {
            $payload['cancellationReasonId'] = $cancellationReasonId;
        } elseif ($reasonText) {
            $payload['reasonText'] = $reasonText;
        } else {
            // Default reason if none provided
            $payload['cancellationReasonId'] = 2; // "I no longer need this product"
        }

        return Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post('https://store.payproglobal.com/api/Subscriptions/Terminate', $payload);
    }
}
