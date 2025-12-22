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
        $type = $request->query('type');

        if ($type === 'upgrade') {
            return $this->showSubscriptionPage('upgrade');
        }

        if ($type === 'downgrade') {
            return $this->showSubscriptionPage('downgrade');
        }

        if ($packageName) {
            $package = Package::where('name', $packageName)->first();
            if (!$package) {
                return redirect()->route('subscription')->with('error', 'Invalid package selected.');
            }

            return $this->showSubscriptionPage('new', $package);
        }

        return $this->showSubscriptionPage('new');
    }

    public function upgrade(Request $request, $package = null)
    {

        if ($request->isMethod('post') && $package) {
            $paymentController = app(PaymentController::class);
            return $paymentController->gatewayCheckout($request, $package, $gateway);
        }

        return $this->showSubscriptionPage('upgrade');
    }

    public function downgrade(Request $request)
    {
        Log::info('=== SubscriptionController::downgrade called ===', [
            'method' => $request->method(),
            'user_id' => Auth::id(),
            'is_post' => $request->isMethod('post'),
            'all_data' => $request->all()
        ]);

        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to manage your subscription.');
        }

        if ($request->isMethod('post')) {
            $targetPackageId = $request->input('package_id');
            $targetPackage = Package::find($targetPackageId);

            if (
                !$targetPackage ||
                !$this->subscriptionService->canDowngradeToPackage($user->package, $targetPackage)
            ) {
                return redirect()->route('user.subscription.details')->with('error', 'Invalid package selected for downgrade.');
            }

            if ($user->paymentGateway && $user->paymentGateway->name === 'Pay Pro Global') {
                $paymentController = app(PaymentController::class);
                return $paymentController->payproglobalDowngrade($request);
            }

            try {
                $gatewayName = $user->paymentGateway->name ?? 'FastSpring';

                Log::info('Processing downgrade via gateway service', [
                    'user_id' => $user->id,
                    'target_package' => $targetPackage->name,
                    'gateway' => $gatewayName
                ]);

                $gatewayInstance = $this->paymentGatewayFactory
                    ->create($gatewayName)
                    ->setUser($user);

                if (!method_exists($gatewayInstance, 'handleDowngrade')) {
                    return redirect()->route('user.subscription.details')
                        ->with('error', "Downgrade is not supported for gateway {$gatewayName}.");
                }

                $downgradeData = $gatewayInstance->handleDowngrade([
                    'package' => $targetPackage->name,
                ], false);

                $result = $this->subscriptionService->scheduleGatewayDowngrade(
                    $user,
                    $targetPackage->name,
                    $gatewayName,
                    $downgradeData
                );

                return redirect()->route('user.subscription.details')
                    ->with('success', $result['message'] ?? "Downgrade to {$targetPackage->name} scheduled successfully.");
            } catch (\Exception $e) {
                Log::error('Failed to schedule downgrade', [
                    'user_id' => $user->id,
                    'target_package_id' => $targetPackageId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return redirect()->route('user.subscription.details')->with('error', 'Failed to schedule downgrade: ' . $e->getMessage());
            }
        }
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

    public function subscriptionDetails(Request $request)
    {
        // Handle PayProGlobal POST callbacks - forward to payment success handler
        if ($request->isMethod('post') && ($request->has('ORDER_STATUS') || $request->has('ORDER_ID') || $request->has('ORDER_ITEMS'))) {
            Log::info('PayProGlobal POST callback detected in subscription-details, forwarding to payments.success handler', [
                'has_order_status' => $request->has('ORDER_STATUS'),
                'has_order_id' => $request->has('ORDER_ID'),
                'order_status' => $request->input('ORDER_STATUS'),
            ]);

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

        if ($type === 'upgrade' && !$context['activeGateway']) {
            return redirect()->route('user.dashboard')
                ->with('error', 'No payment gateway available. Please contact support.');
        }

        return view('subscription.index', $context);
    }
}
