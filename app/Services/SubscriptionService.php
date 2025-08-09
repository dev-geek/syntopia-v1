<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\FastSpringClient;
use App\Services\PaddleClient;
use App\Services\PayProGlobalClient;

class SubscriptionService
{
    private function getGatewayClient(string $gateway)
    {
        $config = config("payment.gateways.{$gateway}");

        return match ($gateway) {
            'FastSpring' => new FastSpringClient($config['username'], $config['password']),
            'Paddle' => new PaddleClient($config['api_key']),
            'PayProGlobal' => new PayProGlobalClient($config['api_key']),
            default => throw new \Exception("Unsupported gateway: {$gateway}")
        };
    }

    public function upgradeSubscription(User $user, string $newPackage, string $prorationBillingMode = null)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            return DB::transaction(function () use ($user, $newPackage, $client, $prorationBillingMode, $currentGateway) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price <= $user->package->price) {
                    throw new \Exception('This is not an upgrade');
                }

                $result = $client->upgradeSubscription(
                    $user->payment_gateway_id,
                    $newPackageModel->getGatewayProductId($currentGateway),
                    $prorationBillingMode
                );

                if (!$result) {
                    throw new \Exception('Failed to process upgrade with payment gateway');
                }

                $user->update([
                    'package_id' => $newPackageModel->id
                ]);

                return [
                    'success' => true,
                    'proration' => $result['proration'] ?? null,
                    'new_package' => $newPackageModel->name,
                    'scheduled' => $result['scheduled_change'] ?? null
                ];
            });
        } catch (\Exception $e) {
            Log::error("Upgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function downgradeSubscription(User $user, string $newPackage, string $prorationBillingMode = null)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            return DB::transaction(function () use ($user, $newPackage, $client, $prorationBillingMode, $currentGateway) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price >= $user->package->price) {
                    throw new \Exception('This is not a downgrade');
                }

                $result = $client->downgradeSubscription(
                    $user->payment_gateway_id,
                    $newPackageModel->getGatewayProductId($currentGateway),
                    $prorationBillingMode
                );

                if (!$result) {
                    throw new \Exception('Failed to process downgrade with payment gateway');
                }

                $user->update([
                    'package_id' => $newPackageModel->id
                ]);

                return [
                    'success' => true,
                    'proration' => $result['proration'] ?? null,
                    'new_package' => $newPackageModel->name,
                    'scheduled' => $result['scheduled_change'] ?? null
                ];
            });
        } catch (\Exception $e) {
            Log::error("Downgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancelSubscription(User $user, int $billingPeriod = 1)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            return DB::transaction(function () use ($user, $client, $billingPeriod) {
                $result = $client->cancelSubscription(
                    $user->payment_gateway_id,
                    $billingPeriod
                );

                if (!$result) {
                    throw new \Exception('Failed to process cancellation with payment gateway');
                }

                // Only update subscription status immediately if cancellation is immediate
                if ($billingPeriod === 0) {
                    $user->update([
                        'is_subscribed' => false
                    ]);
                }

                return [
                    'success' => true,
                    'effective_date' => $result['effective_from'] ?? null,
                    'scheduled' => $billingPeriod !== 0
                ];
            });
        } catch (\Exception $e) {
            Log::error("Cancellation failed for user {$user->id}", [
                'error' => $e->getMessage(),
                'billing_period' => $billingPeriod
            ]);
            throw $e;
        }
    }
}
