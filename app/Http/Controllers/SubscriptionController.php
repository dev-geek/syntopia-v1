<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\PaymentController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    private $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    private function hasActiveSubscription($user)
    {
        if (!$user->is_subscribed || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        // Check if user has an active license
        $activeLicense = $user->userLicence;
        if (!$activeLicense || !$activeLicense->isActive()) {
            return false;
        }

        // Check if license is not expired
        if ($activeLicense->isExpired()) {
            return false;
        }

        return true;
    }

    private function hasScheduledCancellation($user)
    {
        // Check if there's an order with cancellation_scheduled status for this user
        // We check orders by user_id and also verify the user_licence has the subscription_id
        $scheduledCancellation = \App\Models\Order::where('user_id', $user->id)
            ->where('status', 'cancellation_scheduled')
            ->exists();

        return $scheduledCancellation;
    }

    private function canUpgradeSubscription($user)
    {
        return $this->hasActiveSubscription($user) &&
            $user->package &&
            strtolower($user->package->name) !== 'free' &&
            $user->paymentGateway &&
            $user->paymentGateway->is_active;
    }

    /**
     * Get packages that can be upgraded to from the current package
     */
    private function getUpgradeablePackages($currentPackage)
    {
        if (!$currentPackage) {
            return Package::where('price', '>', 0)->get();
        }

        $currentPrice = $currentPackage->price ?? 0;

        // For upgrade, we want packages with higher prices
        return Package::where('price', '>', $currentPrice)
            ->where('name', '!=', $currentPackage->name)
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get packages that can be downgraded to from the current package
     */
    private function getDowngradeablePackages($currentPackage)
    {
        if (!$currentPackage) {
            return collect();
        }

        $currentPrice = $currentPackage->price ?? 0;

        // For downgrade, we want packages with lower prices
        return Package::where('price', '<', $currentPrice)
            ->where('name', '!=', $currentPackage->name)
            ->orderBy('price', 'desc')
            ->get();
    }

    /**
     * Check if a specific package can be upgraded to from current package
     */
    private function canUpgradeToPackage($currentPackage, $targetPackage)
    {
        if (!$currentPackage || !$targetPackage) {
            return false;
        }

        // Can't upgrade to the same package
        if ($currentPackage->name === $targetPackage->name) {
            return false;
        }

        // Can't upgrade to Enterprise (it's custom pricing)
        if (strtolower($targetPackage->name) === 'enterprise') {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        // Can upgrade to packages with higher prices
        return $targetPrice > $currentPrice;
    }

    /**
     * Check if a specific package can be downgraded to from current package
     */
    private function canDowngradeToPackage($currentPackage, $targetPackage)
    {
        if (!$currentPackage || !$targetPackage) {
            return false;
        }

        // Can't downgrade to the same package
        if ($currentPackage->name === $targetPackage->name) {
            return false;
        }

        // Can't downgrade to Enterprise (it's custom pricing)
        if (strtolower($targetPackage->name) === 'enterprise') {
            return false;
        }

        $currentPrice = $currentPackage->price ?? 0;
        $targetPrice = $targetPackage->price ?? 0;

        // Can downgrade to packages with lower prices
        return $targetPrice < $currentPrice;
    }

    /**
     * Determine if user is a returning customer who has previously purchased packages
     */
    private function isReturningCustomer($user)
    {
        return $user->isReturningCustomer();
    }

    /**
     * Get user's purchase history summary
     */
    private function getUserPurchaseHistory($user)
    {
        return $user->getPurchaseHistory();
    }

    /**
     * Determine the appropriate payment gateway for the user
     */
    private function getAppropriatePaymentGateway($user, $type)
    {
        // If user is a returning customer, try to use their original payment gateway
        if ($user->isReturningCustomer() && $user->paymentGateway) {
            // Check if their original gateway is still active
            if ($user->paymentGateway->is_active) {
                return $user->paymentGateway;
            } else {
                // Original gateway is no longer active, but we'll still use it for returning customers
                // This allows them to continue with their preferred payment method
                return $user->paymentGateway;
            }
        }

        // For new customers or returning customers without a payment gateway, use admin's active gateway
        $activeGateway = PaymentGateways::where('is_active', true)->first();

        if (!$activeGateway) {
            // No active gateway found, try to get any available gateway
            $activeGateway = PaymentGateways::first();
        }

        return $activeGateway;
    }

    public function handleSubscription()
    {
        $user = Auth::user();
        return $this->hasActiveSubscription($user)
            ? redirect()->route('user.dashboard')
            : $this->index();
    }

    public function index()
    {
        return $this->showSubscriptionPage('new');
    }

    public function showSubscriptionWithPackage(Request $request)
    {
        $packageName = $request->query('package_name');
        $type = $request->query('type');

        // Check if user is authenticated
        if (!auth()->check()) {
            // Store the intended URL to redirect back after login
            session(['url.intended' => $request->fullUrl()]);
            return redirect()->route('login')->with('info', 'Please log in to continue with your subscription.');
        }

        // Handle different types
        if ($type === 'upgrade') {
            return $this->showSubscriptionPage('upgrade');
        }

        if ($type === 'downgrade') {
            return $this->showSubscriptionPage('downgrade');
        }

        // Handle package_name parameter (existing logic)
        if ($packageName) {
            $package = Package::where('name', $packageName)->first();
            if (!$package) {
                return redirect()->route('home')->with('error', 'Invalid package selected.');
            }

            return $this->showSubscriptionPage('new', $package);
        }

        // Default behavior - show subscription page without specific package
        return $this->showSubscriptionPage('new');
    }

    public function upgrade(Request $request, $package = null)
    {
        $user = Auth::user();

        if ($request->isMethod('post') && $package) {
            // Delegate to PaymentController for checkout
            $paymentController = app(PaymentController::class);
            return $paymentController->fastspringCheckout($request, $package);
        }

        return $this->showSubscriptionPage('upgrade');
    }

    public function downgrade(Request $request)
    {
        return $this->showSubscriptionPage('downgrade');
    }



    public function cancel(Request $request)
    {
        $user = auth()->user();

        try {
            $this->subscriptionService->cancelSubscription($user);
            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Cancellation failed for user {$user->id}", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function subscriptionDetails()
    {
        $user = Auth::user();

        if (!$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        $activeLicense = $user->userLicence;
        $calculatedEndDate = $activeLicense ? $activeLicense->expires_at : null;

        // Check for pending upgrade orders first
        $pendingUpgrade = Order::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'pending_upgrade'])
            ->where(function($query) {
                $query->where('transaction_id', 'like', 'FS-UPGRADE-%')
                      ->orWhere('transaction_id', 'like', 'PPG-UPGRADE-%')
                      ->orWhere('transaction_id', 'like', 'PADDLE-UPGRADE-%');
            })
            ->where('created_at', '>=', now()->subDays(30)) // Only show recent upgrades
            ->first();

        $hasPendingUpgrade = $pendingUpgrade !== null;
        $pendingUpgradeDetails = null;

        // Check for pending downgrade orders
        $pendingDowngrade = Order::where('user_id', $user->id)
            ->where('order_type', 'downgrade')
            ->whereIn('status', ['pending', 'pending_downgrade'])
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->first();
        $hasPendingDowngrade = $pendingDowngrade !== null;
        $pendingDowngradeDetails = null;

        // Determine the current package (gateway-agnostic)
        // Prefer the active license package whenever available
        if ($activeLicense && $activeLicense->package) {
            $package = $activeLicense->package;
        } elseif ($hasPendingDowngrade) {
            if ($activeLicense && $activeLicense->package) {
                $package = $activeLicense->package;
            } else {
                // Resolve original package using order metadata -> payload -> last completed order -> user package
                $originalPackageName = null;
                if ($pendingDowngrade) {
                    if (is_array($pendingDowngrade->metadata) && isset($pendingDowngrade->metadata['original_package'])) {
                        $originalPackageName = $pendingDowngrade->metadata['original_package'];
                    } elseif (isset($pendingDowngrade->payload['original_package'])) {
                        $originalPackageName = $pendingDowngrade->payload['original_package'];
                    }
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

                if ($originalPackageName) {
                    $package = Package::where('name', $originalPackageName)->first() ?: $user->package;
                } else {
                    $package = $user->package;
                }
            }
        } else {
            $package = $user->package;
        }

        $hasActiveSubscription = $this->hasActiveSubscription($user);
        $canUpgrade = $this->canUpgradeSubscription($user);

        if ($activeLicense && $calculatedEndDate) {
            Log::info('License expiration date retrieved', [
                'user_id' => $user->id,
                'package_name' => $package ? $package->name : 'Unknown',
                'end_date' => $calculatedEndDate->toDateTimeString(),
                'has_pending_upgrade' => $hasPendingUpgrade
            ]);
        }

        $hasScheduledCancellation = $this->hasScheduledCancellation($user);

        if ($hasPendingUpgrade) {
            $pendingUpgradeDetails = [
                'target_package' => $pendingUpgrade->package->name ?? 'Unknown',
                'created_at' => $pendingUpgrade->created_at,
                'upgrade_type' => 'subscription_upgrade'
            ];
        }

        if ($hasPendingDowngrade) {
            $originalPackageName = null;
            if (is_array($pendingDowngrade->metadata) && isset($pendingDowngrade->metadata['original_package'])) {
                $originalPackageName = $pendingDowngrade->metadata['original_package'];
            } elseif (isset($pendingDowngrade->payload['original_package'])) {
                $originalPackageName = $pendingDowngrade->payload['original_package'];
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

            $pendingDowngradeDetails = [
                'target_package' => $pendingDowngrade->package->name ?? 'Unknown',
                'original_package' => $originalPackageName,
                'created_at' => $pendingDowngrade->created_at,
                'downgrade_type' => 'subscription_downgrade'
            ];
        }

        // Debug logging for diagnosis
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
                ] : null,
                'resolved_current_package' => $package?->name,
                'pending_downgrade_details' => $pendingDowngradeDetails,
            ]);
        } catch (\Throwable $e) {
            // Ignore logging errors
        }

        // Resolve add-on package IDs to include orders linked by package_id
        $addonPackageIds = \App\Models\Package::whereIn('name', ['Avatar Customization', 'Voice Customization'])
            ->pluck('id')
            ->toArray();

        // Debug: count purchased add-ons
        try {
            $debugPurchasedAddonsCount = \App\Models\Order::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where(function($q) use ($addonPackageIds) {
                    $q->where('order_type', 'addon')
                      ->orWhereNotNull('metadata->addon')
                      ->orWhereIn('package_id', $addonPackageIds)
                      ->orWhere('metadata', 'like', '%"addon"%');
                })
                ->count();
            \Illuminate\Support\Facades\Log::info('[SubscriptionDetails] Purchased addons resolved', [
                'user_id' => $user->id,
                'count' => $debugPurchasedAddonsCount,
                'addon_package_ids' => $addonPackageIds
            ]);
        } catch (\Throwable $e) {}

        return view('subscription.details', [
            'currentPackage' => $package ? $package->name : null,
            'user' => $user,
            'calculatedEndDate' => $calculatedEndDate,
            'hasActiveSubscription' => $hasActiveSubscription,
            'hasScheduledCancellation' => $hasScheduledCancellation,
            'canUpgrade' => $canUpgrade,
            'isExpired' => $calculatedEndDate ? Carbon::now()->gt($calculatedEndDate) : false,
            'hasPendingUpgrade' => $hasPendingUpgrade,
            'pendingUpgradeDetails' => $pendingUpgradeDetails,
            'hasPendingDowngrade' => $hasPendingDowngrade,
            'pendingDowngradeDetails' => $pendingDowngradeDetails,
            'purchasedAddons' => \App\Models\Order::with('package')
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->where(function($q) use ($addonPackageIds) {
                    $q->where('order_type', 'addon')
                      ->orWhereNotNull('metadata->addon')
                      ->orWhereIn('package_id', $addonPackageIds)
                      ->orWhere('metadata', 'like', '%"addon"%');
                })
                ->latest()
                ->get(),
        ]);
    }

    public function updateUserSubscription(Order $order)
    {
        try {
            DB::transaction(function () use ($order) {
                $user = $order->user;
                $package = Package::where('name', $order->package)->firstOrFail();
                $paymentGateway = PaymentGateways::where('name', $order->payment_method)->firstOrFail();

                $user->update([
                    'package_id' => $package->id,
                    'payment_gateway_id' => $paymentGateway->id,
                    'is_subscribed' => true
                ]);

                $order->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

                Log::info('User subscription updated', [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'payment_gateway_id' => $paymentGateway->id,
                    'order_id' => $order->id
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user subscription', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    private function showSubscriptionPage(string $type, $selectedPackage = null)
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        // Determine the appropriate payment gateway based on customer type
        $targetGateway = $this->getAppropriatePaymentGateway($user, $type);

        // For upgrade scenarios, ensure the user has a payment gateway
        if ($type === 'upgrade' && !$targetGateway) {
            return redirect()->route('user.dashboard')
                ->with('error', 'No payment gateway available. Please contact support.');
        }

        $gateways = collect($targetGateway ? [$targetGateway] : []);
        // Exclude one-time add-ons from plan listing
        $packages = Package::select('name', 'price', 'duration', 'features')
            ->whereNotIn('name', ['Avatar Customization', 'Voice Customization'])
            ->get();

        // Get current user's package
        $currentUserPackage = $user->package;

        // Determine which packages are available for upgrade/downgrade
        $upgradeablePackages = $type === 'upgrade' ? $this->getUpgradeablePackages($currentUserPackage) : collect();
        $downgradeablePackages = $type === 'downgrade' ? $this->getDowngradeablePackages($currentUserPackage) : collect();

        // Create a map of package names to their availability status
        $packageAvailability = [];
        foreach ($packages as $package) {
            if ($type === 'upgrade') {
                $packageAvailability[$package->name] = $this->canUpgradeToPackage($currentUserPackage, $package);
            } elseif ($type === 'downgrade') {
                $packageAvailability[$package->name] = $this->canDowngradeToPackage($currentUserPackage, $package);
            } else {
                // For new subscriptions, all packages are available
                $packageAvailability[$package->name] = true;
            }
        }

        // Resolve active add-ons for the current user
        $addonPackageIds = \App\Models\Package::whereIn('name', ['Avatar Customization', 'Voice Customization'])
            ->pluck('id')
            ->toArray();
        $completedAddonOrders = \App\Models\Order::with('package')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where(function($q) use ($addonPackageIds) {
                $q->where('order_type', 'addon')
                  ->orWhereNotNull('metadata->addon')
                  ->orWhereIn('package_id', $addonPackageIds)
                  ->orWhere('metadata', 'like', '%"addon"%');
            })
            ->get();

        $activeAddonSlugs = [];
        foreach ($completedAddonOrders as $order) {
            $name = $order->package->name ?? null;
            if ($name === 'Avatar Customization') {
                $activeAddonSlugs[] = 'avatar_customization';
            } elseif ($name === 'Voice Customization') {
                $activeAddonSlugs[] = 'voice_customization';
            } elseif (is_array($order->metadata) && !empty($order->metadata['addon'])) {
                $activeAddonSlugs[] = strtolower(str_replace('-', '_', $order->metadata['addon']));
            }
        }
        $activeAddonSlugs = array_values(array_unique($activeAddonSlugs));
        $hasActiveAddon = count($activeAddonSlugs) > 0;

        return view('subscription.index', [
            'payment_gateways' => $gateways,
            'currentPackage' => $currentUserPackage ? $currentUserPackage->name : null,
            'currentPackagePrice' => $currentUserPackage ? $currentUserPackage->price : 0,
            'activeGateway' => $targetGateway,
            'currentLoggedInUserPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'userOriginalGateway' => $user->paymentGateway ? $user->paymentGateway->name : null,
            'activeGatewaysByAdmin' => PaymentGateways::where('is_active', true)->pluck('name')->values(),
            'packages' => $packages,
            'pageType' => $type,
            'isUpgrade' => $type === 'upgrade',
            'upgradeEligible' => $type === 'upgrade' && $targetGateway,
            'hasActiveSubscription' => $this->hasActiveSubscription($user),
            'selectedPackage' => $selectedPackage,
            'packageAvailability' => $packageAvailability,
            'upgradeablePackages' => $upgradeablePackages,
            'downgradeablePackages' => $downgradeablePackages,
            'isReturningCustomer' => $this->isReturningCustomer($user),
            'purchaseHistory' => $this->getUserPurchaseHistory($user),
            'selectedPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'isUsingOriginalGateway' => $user->isReturningCustomer() && $user->paymentGateway && $targetGateway && $user->paymentGateway->id === $targetGateway->id,
            'isUsingAdminGateway' => !$user->isReturningCustomer() || !$user->paymentGateway || ($targetGateway && $user->paymentGateway && $user->paymentGateway->id !== $targetGateway->id),
            'activeAddonSlugs' => $activeAddonSlugs,
            'hasActiveAddon' => $hasActiveAddon,
        ]);
    }
}
