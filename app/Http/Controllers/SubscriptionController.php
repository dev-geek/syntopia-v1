<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Package;
use App\Services\SubscriptionService;
use App\Factories\PaymentGatewayFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private SubscriptionService $subscriptionService;
    private PaymentController $paymentController;
    private PaymentGatewayFactory $paymentGatewayFactory;

    public function __construct(SubscriptionService $subscriptionService, PaymentController $paymentController, PaymentGatewayFactory $paymentGatewayFactory)
    {
        $this->subscriptionService = $subscriptionService;
        $this->paymentController = $paymentController;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
    }

    public function handleSubscription()
    {
        $user = Auth::user();
        return $this->subscriptionService->hasActiveSubscription($user)
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

        if ($packageName) {
            $package = Package::where('name', $packageName)->first();
            if (!$package) {
                return redirect()->route('subscription')->with('error', 'Invalid package selected.');
            }

            return $this->showSubscriptionPage('new', $package);
        }

        return $this->showSubscriptionPage('new');
    }




    public function cancel(Request $request)
    {
        $user = auth()->user();

        try {
            $result = $this->subscriptionService->cancelSubscription($user);

            // If it's an AJAX request, return JSON
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result);
            }

            // Otherwise redirect to subscription details page
            $message = $result['message'] ?? 'Subscription cancelled successfully';
            return redirect()->route('user.subscription.details')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error("Cancellation failed for user {$user->id}", ['error' => $e->getMessage()]);
            
            // If it's an AJAX request, return JSON error
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }

            // Otherwise redirect back with error
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    public function subscriptionDetails(Request $request)
    {
        // Handle PayProGlobal POST callbacks - forward to payment success handler
        if ($request->isMethod('post') && ($request->has('ORDER_STATUS') || $request->has('ORDER_ID') || $request->has('ORDER_ITEMS'))) {
            // Merge gateway parameter into request and forward to payment success handler
            $request->merge(['gateway' => 'payproglobal']);
            return $this->paymentController->handleSuccess($request);
        }

        $user = Auth::user();

        if (!$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        $context = $this->subscriptionService->buildSubscriptionDetailsContext($user);

        return view('subscription.details', $context);
    }

    public function updateUserSubscription(Order $order)
    {
        try {
            $this->subscriptionService->updateUserSubscriptionFromOrder($order);

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

        $context = $this->subscriptionService->buildSubscriptionIndexContext($user, $type, $selectedPackage);

        if (!$context['activeGateway']) {
            return redirect()->route('user.dashboard')
                ->with('error', 'No payment gateway available. Please contact support.');
        }

        return view('subscription.index', $context);
    }
}
