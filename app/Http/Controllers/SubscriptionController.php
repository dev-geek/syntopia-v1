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

        $package = $user->package;
        $activeLicense = $user->userLicence;
        $calculatedEndDate = $activeLicense ? $activeLicense->expires_at : null;
        $hasActiveSubscription = $this->hasActiveSubscription($user);
        $canUpgrade = $this->canUpgradeSubscription($user);

        if ($activeLicense && $calculatedEndDate) {
            Log::info('License expiration date retrieved', [
                'user_id' => $user->id,
                'package_name' => $package ? $package->name : 'Unknown',
                'end_date' => $calculatedEndDate->toDateTimeString()
            ]);
        }

        return view('subscription.details', [
            'currentPackage' => $package ? $package->name : null,
            'user' => $user,
            'calculatedEndDate' => $calculatedEndDate,
            'hasActiveSubscription' => $hasActiveSubscription,
            'canUpgrade' => $canUpgrade,
            'isExpired' => $calculatedEndDate ? Carbon::now()->gt($calculatedEndDate) : false
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

        $targetGateway = $type === 'upgrade'
            ? ($user->paymentGateway && $user->paymentGateway->is_active === 1
                ? $user->paymentGateway
                : null)
            : PaymentGateways::where('is_active', true)->first();

        if ($type === 'upgrade' && !$targetGateway) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Your original payment gateway is no longer available. Please contact support.');
        }

        $gateways = collect($targetGateway ? [$targetGateway] : []);
        $packages = Package::select('name', 'price', 'duration', 'features')->get();

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

        return view('subscription.index', [
            'payment_gateways' => $gateways,
            'currentPackage' => $currentUserPackage ? $currentUserPackage->name : null,
            'currentPackagePrice' => $currentUserPackage ? $currentUserPackage->price : 0,
            'activeGateway' => $targetGateway,
            'currentLoggedInUserPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'userOriginalGateway' => $type === 'upgrade' ? ($targetGateway ? $targetGateway->name : null) : null,
            'activeGatewaysByAdmin' => PaymentGateways::where('is_active', true)->pluck('name')->values(),
            'packages' => $packages,
            'pageType' => $type,
            'isUpgrade' => $type === 'upgrade',
            'upgradeEligible' => $type === 'upgrade' && $targetGateway && $targetGateway->is_active,
            'hasActiveSubscription' => $this->hasActiveSubscription($user),
            'selectedPackage' => $selectedPackage,
            'packageAvailability' => $packageAvailability,
            'upgradeablePackages' => $upgradeablePackages,
            'downgradeablePackages' => $downgradeablePackages
        ]);
    }
}
