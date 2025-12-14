<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Models\PaymentGateways;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\License\LicenseApiService;
use App\Factories\PaymentGatewayFactory;

class SubscriptionService
{
    private LicenseApiService $licenseApiService;
    private PaymentGatewayFactory $paymentGatewayFactory;

    public function __construct(LicenseApiService $licenseApiService, PaymentGatewayFactory $paymentGatewayFactory)
    {
        $this->licenseApiService = $licenseApiService;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
    }

    private function getGateway(string $gatewayName)
    {
        return $this->paymentGatewayFactory->create($gatewayName);
    }

    public function upgradeSubscription(User $user, string $newPackage, string $prorationBillingMode = null)
    {
        $currentGateway = $user->paymentGateway->name;
        $gateway = $this->getGateway($currentGateway);

        try {
            return DB::transaction(function () use ($user, $newPackage, $gateway, $prorationBillingMode, $currentGateway) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price <= $user->package->price) {
                    throw new \Exception('This is not an upgrade');
                }

                $result = $gateway->upgradeSubscription(
                    $user->subscription_id,
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
        $gateway = $this->getGateway($currentGateway);

        try {
            return DB::transaction(function () use ($user, $newPackage, $gateway, $prorationBillingMode, $currentGateway) {
                $newPackageModel = Package::where('name', $newPackage)->firstOrFail();

                if ($newPackageModel->price >= $user->package->price) {
                    throw new \Exception('This is not a downgrade');
                }

                if ($currentGateway === 'FastSpring') {
                    $result = $gateway->downgradeSubscriptionForUser(
                        $user,
                        $user->subscription_id,
                        $newPackageModel->getGatewayProductId($currentGateway)
                    );
                } else {
                    $result = $gateway->downgradeSubscription(
                        $user->subscription_id,
                        $newPackageModel->getGatewayProductId($currentGateway),
                        $prorationBillingMode
                    );
                }

                if (!$result || !($result['success'] ?? false)) {
                    throw new \Exception('Failed to process downgrade with payment gateway');
                }

                if (!($result['scheduled_change'] ?? false)) {
                    $user->update([
                        'package_id' => $newPackageModel->id
                    ]);
                }

                return [
                    'success' => true,
                    'proration' => $result['proration'] ?? null,
                    'new_package' => $newPackageModel->name,
                    'scheduled' => $result['scheduled_change'] ?? null,
                    'scheduled_date' => $result['scheduled_date'] ?? null,
                    'message' => $result['message'] ?? null
                ];
            });
        } catch (\Exception $e) {
            Log::error("Downgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancelSubscription(User $user, int $cancellationReasonId = null, string $reasonText = null)
    {
        $user->refresh();
        $currentGateway = $user->paymentGateway->name;
        $normalizedGateway = str_replace(' ', '', ucwords(strtolower($currentGateway)));
        $gateway = $this->getGateway($currentGateway);

        try {
            return DB::transaction(function () use ($user, $gateway, $cancellationReasonId, $reasonText, $currentGateway, $normalizedGateway) {
                if ($normalizedGateway === 'PayProGlobal') {
                    Log::debug('SubscriptionService: Starting PayProGlobal cancellation logic', ['user_id' => $user->id]);

                    $subscriptionId = $user->subscription_id ?? $user->orders()->where('status', 'completed')->latest('created_at')->first()->metadata['subscription_id'] ?? null;
                    Log::debug('SubscriptionService: Initial subscriptionId from user/license', ['user_id' => $user->id, 'subscription_id' => $subscriptionId]);

                    if (is_null($subscriptionId)) {
                        Log::debug('SubscriptionService: subscriptionId is null, attempting to retrieve from latest order metadata', ['user_id' => $user->id]);

                        $latestPayProGlobalOrder = $user->orders()
                            ->whereHas('paymentGateway', function($query) {
                                $query->where('name', 'Pay Pro Global');
                            })
                            ->where('status', 'completed')
                            ->latest('created_at')
                            ->first();

                        if ($latestPayProGlobalOrder) {
                            Log::debug('SubscriptionService: Latest PayProGlobal order found', ['order_id' => $latestPayProGlobalOrder->id, 'order_metadata' => $latestPayProGlobalOrder->metadata]);

                            if (is_array($latestPayProGlobalOrder->metadata) && isset($latestPayProGlobalOrder->metadata['subscription_id'])) {
                                $subscriptionId = $latestPayProGlobalOrder->metadata['subscription_id'];
                                Log::info('SubscriptionService: Retrieved subscription ID from latest PayProGlobal order metadata', [
                                    'user_id' => $user->id,
                                    'order_id' => $latestPayProGlobalOrder->id,
                                    'subscription_id' => $subscriptionId
                                ]);
                            } else {
                                Log::error('SubscriptionService: Latest order metadata subscription_id not found or metadata is not an array.', ['user_id' => $user->id, 'order_id' => $latestPayProGlobalOrder->id, 'metadata_type' => gettype($latestPayProGlobalOrder->metadata)]);
                                throw new \Exception('No active subscription ID found for cancellation.');
                            }
                        } else {
                            Log::error('SubscriptionService: No latest completed Pay Pro Global order found for user.', ['user_id' => $user->id]);
                            throw new \Exception('No active subscription ID found for cancellation.');
                        }
                    } else {
                        Log::debug('SubscriptionService: subscriptionId found directly from user or license', ['user_id' => $user->id, 'subscription_id' => $subscriptionId]);
                    }

                    Log::info('PayProGlobal cancellation - subscription ID being sent', ['user_id' => $user->id, 'subscription_id_sent' => $subscriptionId]);
                }

                $result = $gateway->cancelSubscription($user, $user->subscription_id, $cancellationReasonId, $reasonText);

                if (!($result['success'] ?? false)) {
                    throw new \Exception('Failed to process cancellation with payment gateway');
                }

                // Mark the user's license as cancelled at period end, but keep it active until then
                if ($user->userLicence) {
                    $user->userLicence->update([
                        'status' => 'cancelled_at_period_end',
                        'cancelled_at' => now(),
                    ]);
                }

                // Create a record in orders table for scheduled cancellation
                \App\Models\Order::create([
                    'user_id' => $user->id,
                    'package_id' => $user->package_id,
                    'order_type' => 'cancellation',
                    'status' => 'cancellation_scheduled',
                    'transaction_id' => 'CANCEL-' . $user->id . '-' . uniqid(),
                    'amount' => 0,
                    'currency' => $user->currency ?? 'USD',
                    'payment_method' => $currentGateway,
                    'metadata' => [
                        'original_subscription_id' => $user->subscription_id,
                        'scheduled_termination_date' => $user->userLicence->expires_at->toDateTimeString() ?? null,
                    ]
                ]);

                // The user remains subscribed until the actual end of the billing period.
                // The `is_subscribed` flag will be updated by a scheduled task when the license truly expires.
                return $result;
            });
        } catch (\Exception $e) {
            Log::error("Cancellation failed for user {$user->id}", [
                'error' => $e->getMessage(),
                'subscription_id' => $user->subscription_id ?? 'N/A',
                'gateway' => $currentGateway,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Immediately assign free plan to user without payment gateway checkout
     */
    public function assignFreePlanImmediately(User $user, Package $package): array
    {
        try {
            // Make free plan assignment idempotent to avoid duplicate licenses/orders
            $user->refresh();
            $user->load('userLicence', 'package');

            if ($user->hasActiveSubscription() && (int) $user->package_id === (int) $package->id) {
                Log::info('Skipping free plan assignment - user already has active subscription for this package', [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'user_license_id' => $user->userLicence?->id,
                ]);

                $existingOrderId = $user->orders()
                    ->where('package_id', $package->id)
                    ->where('amount', 0)
                    ->where('status', 'completed')
                    ->latest('created_at')
                    ->value('id');

                return [
                    'success' => true,
                    'order_id' => $existingOrderId,
                    'license_id' => $user->userLicence?->id,
                ];
            }

            if (!$user->tenant_id) {
                Log::error('Cannot assign free plan - user missing tenant_id', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                throw new \Exception('Account is not fully initialized (missing tenant). Please verify your email and try again.');
            }

            return DB::transaction(function () use ($user, $package) {
                // Update user with free package
                $user->update([
                    'package_id' => $package->id,
                    'is_subscribed' => true,
                ]);

                // Create completed order for free plan
                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount' => 0,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'transaction_id' => 'FREE-' . Str::random(10),
                    'metadata' => [
                        'source' => 'free_plan_immediate_assignment',
                        'assigned_at' => now()->toISOString()
                    ]
                ]);

                // Create and activate license for free plan using transaction_id as subscription_id
                $license = $this->licenseApiService->createAndActivateLicense(
                    $user,
                    $package,
                    $order->transaction_id,
                    null,
                    false
                );

                if (!$license) {
                    Log::error('Failed to create license for free plan', [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'tenant_id' => $user->tenant_id,
                    ]);
                    throw new \Exception('Failed to create license for free plan.');
                }

                Log::info('Free plan assigned immediately without checkout', [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'order_id' => $order->id,
                    'license_id' => $license->id
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'license_id' => $license->id
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to assign free plan immediately', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function cancelSubscriptionWithoutExternalId(User $user)
    {
        return DB::transaction(function () use ($user) {
            $userLicense = $user->userLicence;
            if ($userLicense) {
                $userLicense->delete();
            }

            $user->update([
                'is_subscribed' => false,
                'package_id' => null,
                'payment_gateway_id' => null,
                'subscription_id' => null,
                'user_license_id' => null
            ]);

            $order = \App\Models\Order::where('user_id', $user->id)
                ->latest('created_at')
                ->first();

            if ($order) {
                $order->update(['status' => 'canceled']);
            }

            return ['success' => true, 'message' => 'Subscription cancelled successfully'];
        });
    }
}
