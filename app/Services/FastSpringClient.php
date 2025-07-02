<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FastSpringClient
{
    private $username;
    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function upgradeSubscription(string $subscriptionId, string $newProductId)
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->post('https://api.fastspring.com/subscriptions', [
                'subscriptions' => [
                    [
                        'subscription' => $subscriptionId,
                        'product' => $newProductId,
                        'prorate' => true,
                        'preview' => false
                    ]
                ]
            ]);

        return $response->json();
    }

    public function downgradeSubscription(string $subscriptionId, string $newProductId)
    {
        return $this->upgradeSubscription($subscriptionId, $newProductId);
    }

    public function cancelSubscription(string $subscriptionId)
    {
        return Http::withBasicAuth($this->username, $this->password)
            ->post('https://api.fastspring.com/subscriptions/cancel', [
                'subscription' => $subscriptionId
            ]);
    }
}
