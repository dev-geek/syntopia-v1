<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Models\PaymentGateways;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\{
    License\LicenseApiService,
    Payment\PaymentService,
    Payment\Gateways\PaddlePaymentGateway,
};

class SubscriptionService
{
    private LicenseApiService $licenseApiService;
    private TenantAssignmentService $tenantAssignmentService;
    private PaymentService $paymentService;

    public function __construct(
        LicenseApiService $licenseApiService,
        TenantAssignmentService $tenantAssignmentService,
        PaymentService $paymentService,
    )
    {
        $this->licenseApiService = $licenseApiService;
        $this->tenantAssignmentService = $tenantAssignmentService;
        $this->paymentService = $paymentService;
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
                $assignmentResult = $this->tenantAssignmentService->assignTenantWithRetry($user);

                if (!($assignmentResult['success'] ?? false) || empty($assignmentResult['data']['tenantId'] ?? null)) {
                    Log::error('Cannot assign free plan - tenant assignment failed', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'result' => $assignmentResult,
                    ]);

                    $message = $assignmentResult['error_message']
                        ?? 'Account is not fully initialized (missing tenant). Please verify your email and try again.';

                    throw new \Exception($message);
                }

                $user->refresh();
            }

            return DB::transaction(function () use ($user, $package) {
                $activeGateway = $this->getAppropriatePaymentGateway($user);

                $updateData = [
                    'package_id' => $package->id,
                    'is_subscribed' => true,
                ];

                if ($activeGateway) {
                    $updateData['payment_gateway_id'] = $activeGateway->id;

                    if (strtolower($activeGateway->name) === 'paddle') {
                        $paddleCustomerId = null;
                        try {
                            $paddleGateway = app(PaddlePaymentGateway::class);
                            $paddleCustomerId = $paddleGateway->createOrGetCustomer($user);
                            if ($paddleCustomerId) {
                                $updateData['paddle_customer_id'] = $paddleCustomerId;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to create Paddle customer for free plan user', [
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $user->update($updateData);

                $order = $this->paymentService->createFreePlanOrder($user, $package);

                // Create and activate license for free plan using transaction_id as subscription_id
                try {
                    $license = $this->licenseApiService->createAndActivateLicense(
                        $user,
                        $package,
                        $order->transaction_id,
                        $activeGateway ? $activeGateway->id : null,
                        false
                    );

                    if (!$license) {
                        Log::error('Failed to create license for free plan', [
                            'user_id' => $user->id,
                            'package_id' => $package->id,
                            'tenant_id' => $user->tenant_id,
                        ]);
                        throw new \Exception('THIRD_PARTY_API_ERROR');
                    }
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'THIRD_PARTY_API_ERROR')) {
                        throw $e;
                    }
                    Log::error('Failed to create license for free plan', [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'tenant_id' => $user->tenant_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('THIRD_PARTY_API_ERROR');
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

    public function hasActiveSubscription(User $user): bool
    {
        if (!$user->is_subscribed || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        $activeLicense = $user->userLicence;

        // Fallback: if we don't yet have a license but the user is marked subscribed with a paid package,
        // treat this as active for UI purposes.
        if (!$activeLicense) {
            return true;
        }

        if (!$activeLicense->isActive()) {
            return false;
        }

        if ($activeLicense->status === 'cancelled_at_period_end' && $activeLicense->expires_at && $activeLicense->expires_at->isFuture()) {
            return true;
        }

        if ($activeLicense->isExpired()) {
            return false;
        }

        return true;
    }

    public function hasScheduledCancellation(User $user): bool
    {
        return Order::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->whereJsonContains('metadata->cancellation_scheduled', true)
            ->exists();
    }

    public function canUpgradeSubscription(User $user): bool
    {
        return $this->hasActiveSubscription($user)
            && $user->package
            && strtolower($user->package->name) !== 'free'
            && $user->paymentGateway
            && $user->paymentGateway->is_active;
    }

    public function getUpgradeablePackages(?Package $currentPackage)
    {
        if (!$currentPackage) {
            return Package::where('price', '>', 0)->get();
        }

        $currentPrice = $currentPackage->price ?? 0;

        return Package::where('price', '>', $currentPrice)
            ->where('name', '!=', $currentPackage->name)
            ->orderBy('price', 'asc')
            ->get();
    }

    public function getDowngradeablePackages(?Package $currentPackage)
    {
        if (!$currentPackage) {
            return collect();
        }

        $currentPrice = $currentPackage->price ?? 0;

        return Package::where('price', '<', $currentPrice)
            ->where('name', '!=', $currentPackage->name)
            ->orderBy('price', 'desc')
            ->get();
    }

    public function canUpgradeToPackage(?Package $currentPackage, ?Package $targetPackage): bool
    {
        if (!$currentPackage || !$targetPackage) {
            return false;
        }

        if ($currentPackage->name === $targetPackage->name) {
            return false;
        }

        if (strtolower($targetPackage->name) === 'enterprise') {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        return $targetPrice > $currentPrice;
    }

    public function canDowngradeToPackage(?Package $currentPackage, ?Package $targetPackage): bool
    {
        if (!$currentPackage || !$targetPackage) {
            return false;
        }

        if ($currentPackage->name === $targetPackage->name) {
            return false;
        }

        if (strtolower($targetPackage->name) === 'enterprise') {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        return $targetPrice < $currentPrice;
    }

    public function getAppropriatePaymentGateway(User $user): ?PaymentGateways
    {
        if ($user->paymentGateway) {
            return $user->paymentGateway;
        }

        $activeGateway = PaymentGateways::where('is_active', true)->first();

        if (!$activeGateway) {
            $activeGateway = PaymentGateways::first();
        }

        return $activeGateway;
    }

    public function buildSubscriptionDetailsContext(User $user): array
    {
        $activeLicense = $user->userLicence;
        $calculatedEndDate = $activeLicense ? $activeLicense->expires_at : null;

        if ($activeLicense && !$calculatedEndDate && $activeLicense->activated_at) {
            try {
                $calculatedEndDate = $activeLicense->activated_at->copy()->addMonth();
            } catch (\Throwable $e) {
            }
        }

        $isUpgradeLocked = false;

        $pendingUpgrade = Order::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'pending_upgrade'])
            ->where(function ($query) {
                $query->where('transaction_id', 'like', 'FS-UPGRADE-%')
                    ->orWhere('transaction_id', 'like', 'PPG-UPGRADE-%')
                    ->orWhere('transaction_id', 'like', 'PADDLE-UPGRADE-%');
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->first();

        $hasPendingUpgrade = $pendingUpgrade !== null;
        $pendingUpgradeDetails = null;

        $pendingDowngrade = Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'scheduled_downgrade'])
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->first();

        $hasPendingDowngrade = $pendingDowngrade !== null;
        $pendingDowngradeDetails = null;

        if ($hasPendingDowngrade) {
            $targetPackage = is_array($pendingDowngrade->metadata)
                ? ($pendingDowngrade->metadata['downgrade_to'] ?? null)
                : (is_string($pendingDowngrade->metadata) ? (json_decode($pendingDowngrade->metadata, true)['downgrade_to'] ?? null) : null);

            $scheduledEnd = $calculatedEndDate;
            if (!$scheduledEnd && $activeLicense && $activeLicense->activated_at) {
                try {
                    $scheduledEnd = $activeLicense->activated_at->copy()->addMonth();
                } catch (\Throwable $e) {
                }
            }
            $scheduledActivationDate = $scheduledEnd ? $scheduledEnd->format('F j, Y') : null;

            $pendingDowngradeDetails = [
                'target_package' => $targetPackage,
                'scheduled_activation_date' => $scheduledActivationDate,
            ];
        }

        $package = $user->package;

        if (
            $activeLicense &&
            $activeLicense->package &&
            (int) $activeLicense->package_id === (int) $user->package_id
        ) {
            $package = $activeLicense->package;
        }

        $hasActiveSubscription = $this->hasActiveSubscription($user);
        $canUpgrade = $this->canUpgradeSubscription($user);

        if ($activeLicense && $calculatedEndDate) {
            Log::info('License expiration date retrieved', [
                'user_id' => $user->id,
                'package_name' => $package ? $package->name : 'Unknown',
                'end_date' => $calculatedEndDate->toDateTimeString(),
                'has_pending_upgrade' => $hasPendingUpgrade,
            ]);
        }

        $hasScheduledCancellation = $this->hasScheduledCancellation($user);

        if ($hasPendingUpgrade) {
            $pendingUpgradeDetails = [
                'target_package' => $pendingUpgrade->package->name ?? 'Unknown',
                'created_at' => $pendingUpgrade->created_at,
                'upgrade_type' => 'subscription_upgrade',
            ];
        }

        if ($hasPendingDowngrade) {
            $originalPackageName = null;

            if (is_array($pendingDowngrade->metadata) && isset($pendingDowngrade->metadata['original_package_name'])) {
                $originalPackageName = $pendingDowngrade->metadata['original_package_name'];
            } elseif (isset($pendingDowngrade->payload['original_package_name'])) {
                $originalPackageName = $pendingDowngrade->payload['original_package_name'];
            }

            if (!$originalPackageName) {
                $lastCompletedOrder = Order::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('created_at', '<=', $pendingDowngrade->created_at)
                    ->latest()
                    ->first();

                if ($lastCompletedOrder && $lastCompletedOrder->package) {
                    $originalPackageName = $lastCompletedOrder->package->name;
                }
            }

            $targetPackageName = $pendingDowngrade->package->name ?? 'Unknown';
            $targetPackage = $pendingDowngrade->package;

            $targetPackagePrice = null;
            if (is_array($pendingDowngrade->metadata) && isset($pendingDowngrade->metadata['target_package_price'])) {
                $targetPackagePrice = $pendingDowngrade->metadata['target_package_price'];
            } elseif ($targetPackage) {
                $targetPackagePrice = $targetPackage->price;
            }

            $scheduledActivationDate = null;

            if (isset($pendingDowngradeDetails['scheduled_activation_date']) && $pendingDowngradeDetails['scheduled_activation_date']) {
                $scheduledActivationDate = Carbon::parse($pendingDowngradeDetails['scheduled_activation_date']);
            } elseif (is_array($pendingDowngrade->metadata) && isset($pendingDowngrade->metadata['scheduled_activation_date'])) {
                $scheduledActivationDate = Carbon::parse($pendingDowngrade->metadata['scheduled_activation_date']);
            } else {
                $fallback = $calculatedEndDate;

                if (!$fallback && $activeLicense && $activeLicense->activated_at) {
                    try {
                        $fallback = $activeLicense->activated_at->copy()->addMonth();
                    } catch (\Throwable $e) {
                    }
                }

                if ($fallback) {
                    $scheduledActivationDate = $fallback;
                }
            }

            $pendingDowngradeDetails = [
                'target_package' => $targetPackageName,
                'target_package_price' => $targetPackagePrice,
                'original_package' => $originalPackageName,
                'created_at' => $pendingDowngrade->created_at,
                'scheduled_activation_date' => $scheduledActivationDate ? $scheduledActivationDate->format('F j, Y') : null,
                'downgrade_type' => 'subscription_downgrade',
            ];
        }

        try {
            Log::info('Subscription details resolution', [
                'user_id' => $user->id,
                'user_package' => $user->package?->name,
                'active_license_package' => $activeLicense?->package?->name,
                'active_license_expires_at' => $activeLicense?->expires_at?->toDateTimeString(),
                'has_pending_upgrade' => $hasPendingUpgrade,
                'has_pending_downgrade' => $hasPendingDowngrade,
                'pending_downgrade_order' => $pendingDowngrade ? [
                    'id' => $pendingDowngrade->id,
                    'status' => $pendingDowngrade->status,
                    'order_type' => $pendingDowngrade->order_type,
                    'package' => $pendingDowngrade->package?->name,
                    'metadata' => $pendingDowngrade->metadata ?? null,
                    'scheduled_activation_date' => $pendingDowngrade->metadata['scheduled_activation_date'] ?? 'N/A',
                ] : null,
                'resolved_current_package' => $package?->name,
                'pending_downgrade_details' => $pendingDowngradeDetails,
            ]);
        } catch (\Throwable $e) {
        }

        $addonPackageIds = Package::whereIn('name', ['Avatar Customization (Clone Yourself)'])
            ->pluck('id')
            ->toArray();

        try {
            $debugPurchasedAddonsCount = Order::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where(function ($q) use ($addonPackageIds) {
                    $q->where('order_type', 'addon')
                        ->orWhereNotNull('metadata->addon')
                        ->orWhereIn('package_id', $addonPackageIds)
                        ->orWhere('metadata', 'like', '%"addon"%');
                })
                ->count();

            Log::info('[SubscriptionDetails] Purchased addons resolved', [
                'user_id' => $user->id,
                'count' => $debugPurchasedAddonsCount,
                'addon_package_ids' => $addonPackageIds,
            ]);
        } catch (\Throwable $e) {
        }

        $completedAddonOrders = Order::with('package')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where(function ($q) use ($addonPackageIds) {
                $q->where('order_type', 'addon')
                    ->orWhereNotNull('metadata->addon')
                    ->orWhereIn('package_id', $addonPackageIds)
                    ->orWhere('metadata', 'like', '%"addon"%');
            })
            ->get();

        $activeAddonSlugs = [];
        foreach ($completedAddonOrders as $order) {
            $name = $order->package->name ?? null;
            if ($name === 'Avatar Customization (Clone Yourself)') {
                $activeAddonSlugs[] = 'avatar_customization';
            } elseif (is_array($order->metadata) && !empty($order->metadata['addon'])) {
                $activeAddonSlugs[] = strtolower(str_replace('-', '_', $order->metadata['addon']));
            }
        }

        $hasActiveAddon = count($activeAddonSlugs) > 0;

        return [
            'currentPackage' => $package ? $package->name : null,
            'user' => $user,
            'calculatedEndDate' => $calculatedEndDate,
            'hasActiveSubscription' => $hasActiveSubscription,
            'hasScheduledCancellation' => $hasScheduledCancellation,
            'canUpgrade' => $canUpgrade,
            'isUpgradeLocked' => $isUpgradeLocked,
            'isExpired' => $calculatedEndDate ? Carbon::now()->gt($calculatedEndDate) : false,
            'hasPendingUpgrade' => $hasPendingUpgrade,
            'pendingUpgradeDetails' => $pendingUpgradeDetails,
            'hasPendingDowngrade' => $hasPendingDowngrade,
            'pendingDowngradeDetails' => $pendingDowngradeDetails,
            'purchasedAddons' => $completedAddonOrders->sortByDesc('created_at')->values(),
            'activeAddonSlugs' => array_values(array_unique($activeAddonSlugs)),
            'hasActiveAddon' => $hasActiveAddon,
        ];
    }

    public function buildSubscriptionIndexContext(User $user, string $type, ?Package $selectedPackage = null): array
    {
        $isAddonPurchase = request()->has('adon') || request()->query('adon');

        if ($isAddonPurchase) {
            $targetGateway = PaymentGateways::where('name', 'FastSpring')->where('is_active', true)->first();
            if (!$targetGateway) {
                $targetGateway = PaymentGateways::where('name', 'FastSpring')->first();
            }
        } else {
            $targetGateway = $this->getAppropriatePaymentGateway($user);
        }

        $gateways = collect($targetGateway ? [$targetGateway] : []);

        $packages = Package::select('name', 'price', 'duration', 'features')
            ->whereNotIn('name', ['Avatar Customization (Clone Yourself)'])
            ->orderBy('id', 'asc')
            ->get();

        $currentUserPackage = $user->package;

        $packageAvailability = [];
        foreach ($packages as $package) {
            $packageAvailability[$package->name] = true;
        }

        $addonPackageIds = Package::whereIn('name', ['Avatar Customization (Clone Yourself)'])
            ->pluck('id')
            ->toArray();

        $completedAddonOrders = Order::with('package')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where(function ($q) use ($addonPackageIds) {
                $q->where('order_type', 'addon')
                    ->orWhereNotNull('metadata->addon')
                    ->orWhereIn('package_id', $addonPackageIds)
                    ->orWhere('metadata', 'like', '%"addon"%');
            })
            ->get();

        $activeAddonSlugs = [];
        foreach ($completedAddonOrders as $order) {
            $name = $order->package->name ?? null;
            if ($name === 'Avatar Customization (Clone Yourself)') {
                $activeAddonSlugs[] = 'avatar_customization';
            } elseif (is_array($order->metadata) && !empty($order->metadata['addon'])) {
                $activeAddonSlugs[] = strtolower(str_replace('-', '_', $order->metadata['addon']));
            }
        }

        $activeAddonSlugs = array_values(array_unique($activeAddonSlugs));
        $hasActiveAddon = count($activeAddonSlugs) > 0;

        return [
            'payment_gateways' => $gateways,
            'currentPackage' => $currentUserPackage ? $currentUserPackage->name : null,
            'currentPackagePrice' => $currentUserPackage ? $currentUserPackage->price : 0,
            'activeGateway' => $targetGateway,
            'currentLoggedInUserPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'userOriginalGateway' => $user->paymentGateway ? $user->paymentGateway->name : null,
            'activeGatewaysByAdmin' => PaymentGateways::where('is_active', true)->pluck('name')->values(),
            'packages' => $packages,
            'pageType' => 'new',
            'isUpgrade' => false,
            'upgradeEligible' => false,
            'hasActiveSubscription' => $this->hasActiveSubscription($user),
            'selectedPackage' => $selectedPackage,
            'packageAvailability' => $packageAvailability,
            'isReturningCustomer' => $user->isReturningCustomer(),
            'purchaseHistory' => $user->getPurchaseHistory(),
            'selectedPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'isUsingOriginalGateway' => $user->isReturningCustomer() && $user->paymentGateway && $targetGateway && $user->paymentGateway->id === $targetGateway->id,
            'isUsingAdminGateway' => !$user->isReturningCustomer() || !$user->paymentGateway || ($targetGateway && $user->paymentGateway && $user->paymentGateway->id !== $targetGateway->id),
            'activeAddonSlugs' => $activeAddonSlugs,
            'hasActiveAddon' => $hasActiveAddon,
            'upgradeablePackages' => collect(),
            'downgradeablePackages' => collect(),
        ];
    }

    public function cancelSubscription(User $user): array
    {
        return $this->paymentService->handleSubscriptionCancellation($user);
    }

    public function updateUserSubscriptionFromOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $user = $order->user;
            $package = Package::where('name', $order->package)->firstOrFail();
            $paymentGateway = PaymentGateways::where('name', $order->payment_method)->firstOrFail();

            $user->update([
                'package_id' => $package->id,
                'payment_gateway_id' => $paymentGateway->id,
                'is_subscribed' => true,
            ]);

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('User subscription updated', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'payment_gateway_id' => $paymentGateway->id,
                'order_id' => $order->id,
            ]);
        });
    }
}
