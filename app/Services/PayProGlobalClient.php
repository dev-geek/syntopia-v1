<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayProGlobalClient
{
    private $apiKey;
    private $vendorId;
    private $apiSecretKey;

    public function __construct()
    {
        $this->apiKey = config('payment.gateways.PayProGlobal.api_key');
        $this->vendorId = config('payment.gateways.PayProGlobal.vendor_account_id');
        $this->apiSecretKey = config('payment.gateways.PayProGlobal.api_secret_key');
    }

    public function getCheckoutUrl(
        float $amount,
        string $currency,
        string $productId,
        string $customerEmail,
        string $customerFirstName = '',
        string $customerLastName = '',
        string $customData = '',
        string $successUrl = '',
        string $cancelUrl = '',
        bool $useTestMode = true
    ): string
    {
        $baseUrl = 'https://store.payproglobal.com/checkout';

        $params = [
            'products[1][id]' => $productId,
            'email' => $customerEmail,
            'first_name' => $customerFirstName,
            'last_name' => $customerLastName,
            'custom' => $customData,
            'currency' => $currency,
            'use-test-mode' => $useTestMode ? 'true' : 'false',
            'secret-key' => $this->apiSecretKey,
            'success-url' => $successUrl,
            'cancel-url' => $cancelUrl,
        ];

        return $baseUrl . '?' . http_build_query($params);
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
            'vendorAccountId' => (int) $this->vendorId,
            'apiSecretKey' => $this->apiSecretKey,
            'sendCustomerNotification' => $sendCustomerNotification
        ];

        // Add cancellation reason
        if ($cancellationReasonId) {
            $payload['cancellationReasonId'] = $cancellationReasonId;
        } elseif ($reasonText) {
            $payload['reasonText'] = $reasonText;
        } else {
            $payload['cancellationReasonId'] = 2;
        }

        $response = Http::post('https://store.payproglobal.com/api/Subscriptions/Terminate', $payload);

        return $response->json();
    }
}
