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
use App\Services\FastSpringClient;
use App\Services\PaddleClient;
use App\Services\PayProGlobalClient;
use App\Services\LicenseService;

class SubscriptionService
{
    private LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }
    private function getGatewayClient(string $gateway)
    {
        $normalizedGateway = str_replace(' ', '', ucwords(strtolower($gateway))); // Remove spaces after normalizing
        $config = config("payment.gateways.{$normalizedGateway}");

        return match ($normalizedGateway) {
            'FastSpring' => new FastSpringClient($config['username'], $config['password']),
            'Paddle' => new PaddleClient($config['api_key']),
            'PayProGlobal' => new PayProGlobalClient($config['vendor_account_id'], $config['api_secret_key']),
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

    public function cancelSubscription(User $user, int $cancellationReasonId = null, string $reasonText = null)
    {
        $user->refresh(); // Ensure the user model is fresh with the latest subscription_id
        $currentGateway = $user->paymentGateway->name;
        // Normalize the gateway name once at the beginning of the method
        $normalizedGateway = str_replace(' ', '', ucwords(strtolower($currentGateway)));

        $client = $this->getGatewayClient($currentGateway);

        try {
            return DB::transaction(function () use ($user, $client, $cancellationReasonId, $reasonText, $currentGateway, $normalizedGateway) {
                $result = [];

                if ($normalizedGateway === 'PayProGlobal') { // Use the normalized gateway for the condition
                    Log::debug('SubscriptionService: Starting PayProGlobal cancellation logic', ['user_id' => $user->id]);

                    $subscriptionId = $user->subscription_id ?? $user->orders()->where('status', 'completed')->latest('created_at')->first()->metadata['subscription_id'] ?? null;
                    Log::debug('SubscriptionService: Initial subscriptionId from user/license', ['user_id' => $user->id, 'subscription_id' => $subscriptionId]);

                    if (is_null($subscriptionId)) {
                        // Attempt to get subscription_id from the user's latest completed PayProGlobal order's metadata
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

                    // PayProGlobal termination
                    $response = $client->cancelSubscription(
                        $subscriptionId,
                        $cancellationReasonId,
                        $reasonText,
                        true // Send customer notification
                    );

                    if (!($response['isSuccess'] ?? false)) {
                        Log::error('PayProGlobal cancellation failed', [
                            'user_id' => $user->id,
                            'subscription_id' => $user->subscription_id,
                            'response' => $response,
                        ]);
                        throw new \Exception('Failed to cancel subscription with PayProGlobal');
                    }

                    $result = [
                        'success' => true,
                        'effective_date' => null, // PayProGlobal terminates at end of period by default
                        'scheduled' => true, // Always scheduled for end of period
                    ];

                } else {
                    // Existing logic for other gateways (Paddle, FastSpring)
                    // Assuming `billingPeriod` of 1 means end of current period, 0 means immediate
                    $billingPeriod = 1; // Default to end of current billing period

                    $result = $client->cancelSubscription(
                        $user->subscription_id,
                        $billingPeriod
                    );

                    if (!$result) {
                        throw new \Exception('Failed to process cancellation with payment gateway');
                    }
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
            return DB::transaction(function () use ($user, $package) {
                // Ensure user has tenant_id - required for license creation
                // This MUST succeed before any changes are made
                if (!$user->tenant_id) {
                    Log::warning('User missing tenant_id during free plan assignment, attempting to create', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                    
                    // Try to create tenant_id if user has subscriber_password
                    if (!$user->subscriber_password) {
                        Log::error('User missing both tenant_id and subscriber_password', [
                            'user_id' => $user->id
                        ]);
                        throw new \Exception('Account setup incomplete. Please verify your email address first.');
                    }

                    // Attempt to create tenant - if this fails, the entire transaction will rollback
                    $tenantId = $this->createTenantForUser($user, $user->subscriber_password);
                    if (!$tenantId) {
                        Log::error('Failed to create tenant_id during free plan assignment', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                        // This exception will cause the entire transaction to rollback
                        throw new \Exception('Failed to create tenant. Account setup incomplete. Please verify your email address first.');
                    }

                    // Update tenant_id - this is part of the transaction
                    $user->update(['tenant_id' => $tenantId]);
                    $user->refresh();
                    Log::info('Tenant_id created during free plan assignment', [
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id
                    ]);
                }

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
                // If this fails, the transaction will rollback everything above
                $license = $this->licenseService->createAndActivateLicense(
                    $user,
                    $package,
                    $order->transaction_id,
                    null,
                    false
                );

                if (!$license) {
                    Log::error('License creation failed for free plan', [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'tenant_id' => $user->tenant_id
                    ]);
                    // This exception will cause the entire transaction to rollback
                    throw new \Exception('Failed to create license for free plan. All changes have been reverted.');
                }

                // Refresh user to ensure license relationship is loaded
                $user->refresh();
                $user->load('userLicence');

                // Verify subscription is active
                if (!$user->hasActiveSubscription()) {
                    Log::error('Free plan assigned but hasActiveSubscription returns false', [
                        'user_id' => $user->id,
                        'is_subscribed' => $user->is_subscribed,
                        'has_license' => (bool)$user->userLicence,
                        'license_active' => $user->userLicence?->isActive()
                    ]);
                    // This exception will cause the entire transaction to rollback
                    throw new \Exception('Subscription verification failed. All changes have been reverted.');
                }

                Log::info('Free plan assigned immediately without checkout', [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'order_id' => $order->id,
                    'license_id' => $license->id,
                    'tenant_id' => $user->tenant_id,
                    'has_active_subscription' => $user->hasActiveSubscription()
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'license_id' => $license->id
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to assign free plan immediately - transaction rolled back', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception so the caller knows the operation failed
            throw $e;
        }
    }

    /**
     * Create tenant for user if it doesn't exist
     */
    private function createTenantForUser(User $user, string $plainPassword): ?string
    {
        $baseUrl = rtrim(config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp'), '/');
        
        try {
            // Create the tenant
            $createResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders([
                    'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($baseUrl . '/api/partner/tenant/create', [
                    'name' => $user->name,
                    'regionCode' => 'CN',
                    'adminName' => $user->name,
                    'adminEmail' => $user->email,
                    'adminPhone' => '',
                    'adminPassword' => $plainPassword,
                    'appIds' => [1],
                ]);

            $createJson = $createResponse->json();
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                Log::error('User already registered in tenant system', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return null;
            }

            if (!$createResponse->successful()) {
                Log::error('Failed to create tenant', [
                    'user_id' => $user->id,
                    'status' => $createResponse->status(),
                    'response' => $createResponse->body()
                ]);
                return null;
            }

            $tenantId = $createJson['data']['tenantId'] ?? null;
            if (!$tenantId) {
                Log::error('Failed to extract tenantId from create response', [
                    'user_id' => $user->id,
                    'response' => $createJson
                ]);
                return null;
            }

            // Bind password
            $passwordBindResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders([
                    'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($baseUrl . '/api/partner/tenant/user/password/bind', [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]);

            if (!$passwordBindResponse->successful()) {
                Log::error('Failed to bind password after tenant creation', [
                    'user_id' => $user->id,
                    'status' => $passwordBindResponse->status(),
                    'response' => $passwordBindResponse->body()
                ]);
                // Still return tenantId even if password bind fails
            }

            Log::info('Tenant created and password bound successfully', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);

            return $tenantId;
        } catch (\Exception $e) {
            Log::error('Exception while creating tenant', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
