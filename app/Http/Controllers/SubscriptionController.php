<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\Package;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function handleSubscription()
    {
        $user = auth()->user();

        // Check if user has an active package
        if ($user && $user->package && $user->subscription_starts_at && $user->is_subscribed === true) {
            // User has an active subscription, redirect to their dashboard
            return redirect()->route('user.dashboard');
        } else if ($user && $user->package && $user->package === 'Free') {
            // User is on the free plan
            return redirect()->route('user.dashboard');
        } else {
            // User doesn't have an active package, show pricing page
            return $this->index();
        }
    }

    public function show()
    {
        return $this->showSubscriptionPage();
    }

    public function index()
    {
        return $this->showSubscriptionPage('new');
    }
    public function upgradeSubscription()
    {
        $user = Auth::user();

        if (!$user->package || !$user->paymentGateway) {
            return redirect()->back()
                ->with('error', 'You need an active subscription before you can upgrade.');
        }

        // Check if user has an active subscription
        if ($user->subscription_starts_at === null) {
            return redirect()->back()
                ->with('error', 'Your subscription has expired. Please start a new subscription.');
        }

        // Check if original gateway is still active
        if (!$user->paymentGateway->is_active) {
            return redirect()->back()
                ->with('error', 'Your original payment gateway is no longer available. Please contact support for assistance with upgrades.');
        }

        return $this->showSubscriptionPage('upgrade');
    }

    /**
     * Show downgrade subscription page
     */
    public function downgradeSubscription()
    {
        $user = Auth::user();

        if (!$user->package || !$user->paymentGateway) {
            return redirect()->back()
                ->with('error', 'You need an active subscription before you can downgrade.');
        }

        // Check if user has an active subscription
        if ($user->subscription_starts_at === null) {
            return redirect()->back()
                ->with('error', 'Your subscription has expired. Please start a new subscription.');
        }

        // Check if original gateway is still active
        if (!$user->paymentGateway->is_active) {
            return redirect()->back()
                ->with('error', 'Your original payment gateway is no longer available. Please contact support for assistance with downgrades.');
        }

        return $this->showSubscriptionPage('downgrade');
    }

    /**
     * Common method to show subscription page
     */
    private function showSubscriptionPage(string $type = 'new')
    {
        $user = Auth::user();

        // Check if user has required role
        if (!$user->hasRole(['User', 'Sub Admin'])) {
            return redirect()->route('admin.dashboard');
        }
        
        // For new subscriptions, use the currently active gateway
        if ($type === 'upgrade' || $type === 'downgrade') {
            $targetGateway = $user->paymentGateway;
            $selectedGatewayName = $targetGateway ? $targetGateway->name : null;
            
            // Ensure the user's original gateway is still available/active
            if (!$targetGateway || !$targetGateway->is_active) {
                return redirect()->route('subscription.details')
                    ->with('error', 'Your original payment gateway is no longer available. Please contact support for assistance with plan changes.');
            }
        } else {
            // For new subscriptions, use currently active gateway
            $targetGateway = PaymentGateways::where('is_active', true)->first();
            $selectedGatewayName = $targetGateway ? $targetGateway->name : null;
        }

        // Create gateway collection for the view
        $gateways = collect();
        if ($targetGateway) {
            $gateways->push($targetGateway);
        }

        $activeGatewaysByAdmin = PaymentGateways::where('is_active', true)
            ->whereNotNull('name')
            ->pluck('name')
            ->filter()
            ->values();

        // Get packages and filter for upgrades/downgrades
        $packagesQuery = Package::select('id', 'name', 'price', 'duration', 'features');
        
        if ($type === 'upgrade') {
            $currentPackagePrice = optional($user->package)->price ?? 0;
            // Only show packages with higher price for upgrades
            $packagesQuery->where('price', '>', $currentPackagePrice);
        } elseif ($type === 'downgrade') {
            $currentPackagePrice = optional($user->package)->price ?? 0;
            // Only show packages with lower price for downgrades
            $packagesQuery->where('price', '<', $currentPackagePrice);
        }
        
        $packages = $packagesQuery->get();

        // Add messaging for empty package list
        $message = null;
        if ($type === 'upgrade' && $packages->isEmpty()) {
            $message = 'You are already on the highest available plan. No upgrades are available at this time.';
        } elseif ($type === 'downgrade' && $packages->isEmpty()) {
            $message = 'You are already on the lowest available plan. No downgrades are available at this time.';
        }

        return view('subscription.index', [
            'payment_gateways' => $gateways,
            'currentPackage' => $user->package->name ?? null,
            'currentPackagePrice' => optional($user->package)->price ?? 0,
            'activeGateway' => $targetGateway,
            'currentLoggedInUserPaymentGateway' => $selectedGatewayName,
            'userOriginalGateway' => ($type === 'upgrade' || $type === 'downgrade') ? $selectedGatewayName : null,
            'activeGatewaysByAdmin' => $activeGatewaysByAdmin,
            'packages' => $packages,
            'pageType' => $type,
            'isUpgrade' => $type === 'upgrade',
            'isDowngrade' => $type === 'downgrade',
            'upgradeEligible' => ($type === 'upgrade' || $type === 'downgrade') && $targetGateway && $targetGateway->is_active,
            'upgradeMessage' => $message, 
        ]);
    }

    public function subscriptionDetails()
    {
        $user = Auth::user();

        // Check if user has required role
        if (!$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        $currentLoggedInUserPackage = $user->package->name ?? null;
        $package = $user->package;
        $calculatedEndDate = null;

        // Calculate end date if user has a package and start date
        if ($package && $user->subscription_starts_at) {
            // Get duration in days and months
            $durationInDays = $package->getDurationInDays();
            $monthlyDuration = $package->getMonthlyDuration();

            // Log debug information to diagnose the issue
            Log::info('Subscription Details Calculation:', [
                'package_name' => $package->name,
                'duration_string' => $package->duration,
                'monthly_duration' => $monthlyDuration,
                'duration_in_days' => $durationInDays,
                'start_date' => $user->subscription_starts_at->toDateTimeString(),
                'calculated_end_date' => $durationInDays !== null ? $user->subscription_starts_at->copy()->addDays($durationInDays)->toDateTimeString() : null
            ]);

            // Prioritize monthly duration for monthly packages
            if ($monthlyDuration !== null) {
                $calculatedEndDate = $user->subscription_starts_at->copy()->addMonths($monthlyDuration);
                Log::info('Using monthly duration', ['months' => $monthlyDuration, 'end_date' => $calculatedEndDate->toDateTimeString()]);
            } elseif ($durationInDays !== null) {
                $calculatedEndDate = $user->subscription_starts_at->copy()->addDays($durationInDays);
                Log::info('Using duration in days', ['days' => $durationInDays, 'end_date' => $calculatedEndDate->toDateTimeString()]);
            } else {
                Log::warning('No valid duration found for package', ['package_id' => $package->id, 'duration' => $package->duration]);
            }
        } else {
            Log::warning('No package or start date found for user', [
                'user_id' => $user->id,
                'has_package' => !is_null($package),
                'has_start_date' => !is_null($user->subscription_starts_at)
            ]);
        }

        return view('subscription.details', [
            'currentPackage' => $currentLoggedInUserPackage,
            'user' => $user,
            'calculatedEndDate' => $calculatedEndDate
        ]);
    }

    public function handlePaymentWebhook(Request $request, $gateway)
    {
        Log::info("Webhook received from $gateway", [
            'headers' => $request->headers->all(),
            'data' => $request->all()
        ]);

        try {
            $this->paymentService->setGateway($gateway);
            $result = $this->paymentService->handlePaymentCallback($request->all());

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("$gateway webhook processing error", ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function paddleCheckout(Request $request, $packageName)
    {
        try {
            $this->paymentService->setGateway('Paddle');
            $result = $this->paymentService->createPaymentSession($packageName, auth()->user());

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function payProGlobalCheckout(Request $request, $packageName)
    {
        try {
            $this->paymentService->setGateway('Pay Pro Global');
            $result = $this->paymentService->createPaymentSession($packageName, auth()->user());

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function paymentSuccess(Request $request)
    {
        $gateway = $request->query('gateway');
        $orderId = $request->query('order_id') ?? $request->query('order');
        $source = $request->query('source');

        Log::info("Payment success callback", [
            'gateway' => $gateway,
            'order_id' => $orderId,
            'source' => $source,
            'all_params' => $request->all()
        ]);

        // Handle FastSpring return URL (user is redirected back to our site)
        if ($source === 'fastspring' || $gateway === 'fastspring') {
            $order = null;

            if ($orderId) {
                $order = Order::where('id', $orderId)
                    ->orWhere('transaction_id', $orderId)
                    ->first();
            }

            if ($order) {
                if ($order->status === 'completed') {
                    session()->flash('success', 'Your subscription has been activated successfully!');
                } else {
                    // Check if webhook has already processed this
                    if (config('payment.gateways.FastSpring.use_webhook')) {
                        session()->flash('info', 'Your payment is being processed. Your subscription will be activated shortly.');
                    } else {
                        // Process immediately if not using webhooks
                        DB::transaction(function () use ($order) {
                            $order->update([
                                'status' => 'completed',
                                'transaction_id' => $order->transaction_id ?? ('FS-' . Str::random(10)),
                                'payment_method' => 'FastSpring'
                            ]);
                            $this->updateUserSubscription($order);
                        });
                        session()->flash('success', 'Your subscription has been activated successfully!');
                    }
                }
            } else {
                // No order found, but payment was successful
                Log::warning('FastSpring success callback received but no matching order found', [
                    'order_id' => $orderId,
                    'all_params' => $request->all()
                ]);
                session()->flash('info', 'Your payment was successful. If you don\'t see your subscription activated, please contact support.');
            }

            return redirect()->route('dashboard');
        }

        // Handle other payment gateways
        $order = $orderId ? Order::find($orderId) : null;

        if ($order) {
            if ($order->status === 'completed') {
                session()->flash('success', 'Your subscription has been activated successfully!');
            } else {
                session()->flash('info', 'Your payment is being processed. Your subscription will be activated shortly.');
            }
        } else {
            session()->flash('info', 'Payment successful! Your subscription is being processed.');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Handle cancelled payment
     */
    public function paymentCancel(Request $request)
    {
        $gateway = $request->query('gateway');

        Log::info("Payment cancelled", [
            'gateway' => $gateway,
            'data' => $request->all()
        ]);

        // Flash message
        session()->flash('error', 'Your payment was cancelled. Please try again or contact support if you need assistance.');

        // Redirect back to dashboard
        return redirect()->route('dashboard');
    }
}