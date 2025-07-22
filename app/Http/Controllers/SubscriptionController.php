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
        if (!$user->is_subscribed || !$user->subscription_starts_at || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        $startDate = Carbon::parse($user->subscription_starts_at);
        $durationInDays = $user->package->getDurationInDays();
        $endDate = $durationInDays ? $startDate->copy()->addDays($durationInDays) : null;

        return $endDate ? Carbon::now()->lte($endDate) : $user->is_subscribed;
    }

    private function canUpgradeSubscription($user)
    {
        return $this->hasActiveSubscription($user) &&
            strtolower($user->package->name) !== 'free' &&
            $user->paymentGateway &&
            $user->paymentGateway->is_active;
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

        // Check if user is authenticated
        if (!auth()->check()) {
            // Store the intended URL to redirect back after login
            session(['url.intended' => $request->fullUrl()]);
            return redirect()->route('login')->with('info', 'Please log in to continue with your subscription.');
        }

        // Validate package name
        if (!$packageName) {
            return redirect()->route('home')->with('error', 'No package selected.');
        }

        $package = Package::where('name', $packageName)->first();
        if (!$package) {
            return redirect()->route('home')->with('error', 'Invalid package selected.');
        }

        return $this->showSubscriptionPage('new', $package);
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
        $user = auth()->user();
        $newPackage = $request->input('package');

        try {
            $result = $this->subscriptionService->downgradeSubscription($user, $newPackage);
            return response()->json([
                'success' => true,
                'message' => 'Subscription downgraded successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Downgrade failed for user {$user->id}", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
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
        $calculatedEndDate = null;
        $hasActiveSubscription = $this->hasActiveSubscription($user);
        $canUpgrade = $this->canUpgradeSubscription($user);

        if ($package && $user->subscription_starts_at) {
            $durationInDays = $package->getDurationInDays();
            if ($durationInDays) {
                $calculatedEndDate = Carbon::parse($user->subscription_starts_at)->addDays($durationInDays);
                Log::info('Subscription end date calculated', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'end_date' => $calculatedEndDate->toDateTimeString()
                ]);
            }
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
                    'is_subscribed' => true,
                    'subscription_starts_at' => now(),
                    'subscription_ends_at' => null
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

        return view('subscription.index', [
            'payment_gateways' => $gateways,
            'currentPackage' => $user->package ? $user->package->name : null,
            'currentPackagePrice' => $user->package ? $user->package->price : 0,
            'activeGateway' => $targetGateway,
            'currentLoggedInUserPaymentGateway' => $targetGateway ? $targetGateway->name : null,
            'userOriginalGateway' => $type === 'upgrade' ? ($targetGateway ? $targetGateway->name : null) : null,
            'activeGatewaysByAdmin' => PaymentGateways::where('is_active', true)->pluck('name')->values(),
            'packages' => $packages,
            'pageType' => $type,
            'isUpgrade' => $type === 'upgrade',
            'upgradeEligible' => $type === 'upgrade' && $targetGateway && $targetGateway->is_active,
            'hasActiveSubscription' => $this->hasActiveSubscription($user),
            'selectedPackage' => $selectedPackage
        ]);
    }
}
