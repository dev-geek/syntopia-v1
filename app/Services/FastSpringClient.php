<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class FastSpringClient
{
    private $username;
    private $password;

    private function resolvePackageIdByName(string $name): ?int
    {
        $package = Package::where('name', $name)->first();
        return $package?->id;
    }

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

    /**
     * Prepare for FastSpring popup checkout for downgrade
     *
     * @param User $user The user requesting the downgrade
     * @param string $subscriptionId The FastSpring subscription ID
     * @param string $newProductId The ID of the product to downgrade to
     * @return array Result containing success status and message
     */
    public function downgradeSubscription(User $user, string $subscriptionId, string $newProductId)
    {
        try {
            // Create a pending order to track this transaction
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => Package::where('name', $newProductId)->first()->id ?? null,
                'order_number' => 'downgrade-' . $user->id . '-' . time(),
                'transaction_id' => 'downgrade-' . $subscriptionId . '-' . time(),
                'amount' => 0, // Will be set by FastSpring
                'payment_gateway_id' => PaymentGateways::where('name', 'FastSpring')->first()->id ?? null,
                'status' => 'pending',
                'order_type' => 'downgrade',
                'metadata' => [
                    'subscription_id' => $subscriptionId,
                    'original_package' => ($user->userLicence && $user->userLicence->package) ? $user->userLicence->package->name : ($user->package->name ?? 'Unknown'),
                    'downgrade_to' => Package::where('id', $this->resolvePackageIdByName($newProductId))->value('name') ?? $newProductId,
                    'downgrade_type' => 'subscription_downgrade',
                    'temp_transaction_id' => true
                ]
            ]);

            Log::info('Prepared FastSpring downgrade checkout', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'new_product' => $newProductId,
                'order_id' => $order->id
            ]);

            // Return success - the frontend will handle the popup
            return [
                'success' => true,
                'requires_popup' => true,
                'package_name' => $newProductId,
                'action' => 'downgrade',
                'message' => 'Preparing downgrade checkout...'
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to prepare FastSpring downgrade', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to prepare downgrade. Please try again or contact support.'
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId, int $billingPeriod = 1)
    {
        // Validate billing period
        if (!in_array($billingPeriod, [0, 1])) {
            throw new \InvalidArgumentException('Billing period must be 0 (immediate) or 1 (end of period)');
        }

        return Http::withBasicAuth($this->username, $this->password)
            ->delete("https://api.fastspring.com/subscriptions/{$subscriptionId}?billingPeriod={$billingPeriod}");
    }

    private function sendSubscriptionChange(string $subscriptionId, string $productId, bool $prorate)
    {
        return Http::withBasicAuth($this->username, $this->password)
            ->post('https://api.fastspring.com/subscriptions', [
                'subscriptions' => [
                    [
                        'subscription' => $subscriptionId,
                        'product' => $productId,
                        'prorate' => $prorate,
                        'preview' => false
                    ]
                ]
            ]);
    }

    /**
     * Create friendly messages from FastSpring errors
     */
    private function getErrorMessage($response)
    {
        $json = $response->json();

        // If FastSpring sends structured errors
        if (isset($json['error']) && is_string($json['error'])) {
            return match ($json['error']) {
                'subscription.not_found' => 'We could not find your subscription. Please contact support.',
                'product.not_found' => 'The selected plan could not be found. Please try again.',
                default => 'An error occurred while processing your request. Please try again.'
            };
        }

        // Fallback for unexpected responses
        return 'An unexpected error occurred. Please contact support if the problem persists.';
    }
}
