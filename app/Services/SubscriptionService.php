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

    public function upgradeSubscription(User $user, string $newPackage)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            DB::transaction(function () use ($user, $newPackage, $client) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price <= $user->package->price) {
                    throw new \Exception('This is not an upgrade');
                }

                $result = $client->upgradeSubscription(
                    $user->payment_gateway_id,
                    $newPackageModel->getGatewayProductId($currentGateway)
                );

                $user->update([
                    'package_id' => $newPackageModel->id,
                    'subscription_starts_at' => now()
                ]);

                return [
                    'success' => true,
                    'proration' => $result['proration'] ?? null,
                    'new_package' => $newPackageModel->name
                ];
            });
        } catch (\Exception $e) {
            Log::error("Upgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function downgradeSubscription(User $user, string $newPackage)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            DB::transaction(function () use ($user, $newPackage, $client) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price >= $user->package->price) {
                    throw new \Exception('This is not a downgrade');
                }

                $result = $client->downgradeSubscription(
                    $user->payment_gateway_id,
                    $newPackageModel->getGatewayProductId($currentGateway)
                );

                $user->update([
                    'package_id' => $newPackageModel->id,
                    'subscription_starts_at' => now()
                ]);

                return [
                    'success' => true,
                    'new_package' => $newPackageModel->name
                ];
            });
        } catch (\Exception $e) {
            Log::error("Downgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancelSubscription(User $user)
    {
        $currentGateway = $user->paymentGateway->name;
        $client = $this->getGatewayClient($currentGateway);

        try {
            DB::transaction(function () use ($user, $client) {
                $client->cancelSubscription($user->payment_gateway_id);

                $user->update([
                    'is_subscribed' => false,
                    'subscription_ends_at' => now()
                ]);

                return ['success' => true];
            });
        } catch (\Exception $e) {
            Log::error("Cancellation failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
