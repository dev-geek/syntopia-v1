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
        if ($user && $user->package && $user->subscription_ends_at && $user->subscription_ends_at > now()) {
            // User has an active subscription, redirect to their dashboard
            return redirect()->route('profile');
        } else if ($user && $user->package && $user->package === 'Free') {
            // User is on the free plan
            return redirect()->route('profile');
        } else {
            // User doesn't have an active package, show pricing page
            return $this->index();
        }
    }

    /**
     * Display the pricing page
     */
    public function index()
    {
        $user = Auth::user();

        $currentLoggedInUserPaymentGateway = optional($user->paymentGateway)->name ?? null;

        // Create a collection with just the user's original gateway
        $userGateway = new PaymentGateways();
        $userGateway->name = $currentLoggedInUserPaymentGateway;
        $filteredGateways = collect([$userGateway]);

        $activeGateway = PaymentGateways::where('is_active', true)->first();

        $activeGatewaysByAdmin = PaymentGateways::where('is_active', true)
                            ->whereNotNull('name')
                            ->pluck('name')
                            ->filter() // Removes nulls, empty strings
                            ->values();

        $packages = Package::select('name', 'price', 'duration', 'features')->get();

        return view('subscription.index', [
            'payment_gateways' => $filteredGateways,
            'currentPackage' => $user->package->name ?? null,
            'activeGateway' => $activeGateway,
            'currentLoggedInUserPaymentGateway' => $currentLoggedInUserPaymentGateway,
            'activeGatewaysByAdmin' => $activeGatewaysByAdmin,
            'packages' => $packages,
        ]);

    }

    /**
     * Handle webhook callbacks from payment gateways
     */
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

    /**
     * Handle Paddle checkout API request
     */
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

    /**
     * Handle PayProGlobal checkout API request
     */
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

    /**
     * Handle successful payment
     */
    public function paymentSuccess(Request $request)
    {
        $gateway = $request->query('gateway');
        $orderId = $request->query('order');

        Log::info("Payment success callback", [
            'gateway' => $gateway,
            'order' => $orderId,
            'data' => $request->all()
        ]);

        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
        }

        if ($order && $order->status === 'completed') {
            // Order has already been completed via webhook
            session()->flash('success', 'Your subscription has been activated successfully!');
        } else {
            // Order is still pending webhook confirmation
            session()->flash('info', 'Your payment is being processed. Your subscription will be activated shortly.');
        }

        if ($gateway === 'fastspring' && $orderId && config('payment.gateways.FastSpring.use_redirect_callback')) {
            DB::transaction(function () use ($orderId) {
                $order = Order::lockForUpdate()->find($orderId);
                if ($order && $order->status === 'pending') {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => 'FS-' . Str::random(10),
                        'payment_method' => 'FastSpring'
                    ]);

                    // Update user subscription
                    $this->updateUserSubscription($order);
                }
            }, 3);
        }

        // Redirect
        return redirect()->route('subscriptions.index');
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

        // Redirect back to subscription page
        return redirect()->route('subscriptions.index');
    }
}
