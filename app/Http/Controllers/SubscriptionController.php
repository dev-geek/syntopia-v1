<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\Package;
use App\Services\PaymentService;
use Carbon\Carbon;
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

        if ($user && $user->package && $user->subscription_starts_at && $user->subscription_starts_at > now()) {
            return redirect()->route('profile');
        } else if ($user && $user->package && $user->package->isFree()) {
            return redirect()->route('profile');
        } else {
            return $this->index();
        }
    }

    /**
     * Display the pricing page
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->hasRole(['User', 'Sub Admin'])) {
            return redirect()->route('admin.dashboard');
        }

        $currentLoggedInUserPaymentGateway = optional($user->paymentGateway)->name ?? null;
        $userGateway = new PaymentGateways();
        $userGateway->name = $currentLoggedInUserPaymentGateway;
        $filteredGateways = collect([$userGateway]);
        $activeGateway = PaymentGateways::where('is_active', true)->first();
        $activeGatewaysByAdmin = PaymentGateways::where('is_active', true)
            ->whereNotNull('name')
            ->pluck('name')
            ->filter()
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

    public function subscriptionDetails()
    {
        $user = Auth::user();

        if (!$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        $currentLoggedInUserPackage = $user->package->name ?? null;
        $package = $user->package;
        $calculatedEndDate = null;

        if ($package && $user->subscription_starts_at) {
            $durationInDays = $package->getDurationInDays();
            $monthlyDuration = $package->getMonthlyDuration();

            Log::info('Subscription Details Calculation:', [
                'package_name' => $package->name,
                'duration_string' => $package->duration,
                'monthly_duration' => $monthlyDuration,
                'duration_in_days' => $durationInDays,
                'start_date' => $user->subscription_starts_at->toDateTimeString(),
                'calculated_end_date' => $durationInDays !== null ? $user->subscription_starts_at->copy()->addDays($durationInDays)->toDateTimeString() : null
            ]);

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

        if ($source === 'fastspring' || $gateway === 'fastspring') {
            $order = null;
            if ($orderId) {
                $order = Order::where('id', $orderId)->orWhere('transaction_id', $orderId)->first();
            }
            if ($order) {
                if ($order->status === 'completed') {
                    session()->flash('success', 'Your subscription has been activated successfully!');
                } else {
                    if (config('payment.gateways.FastSpring.webhook')) {
                        session()->flash('info', 'Your payment is being processed. Your subscription will be activated shortly.');
                    } else {
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
                Log::warning('FastSpring success callback received but no matching order found', [
                    'order_id' => $orderId,
                    'all_params' => $request->all()
                ]);
                session()->flash('info', 'Your payment was successful. If you don\'t see your subscription activated, please contact support.');
            }
            return redirect()->route('user.dashboard');
        }

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
        return redirect()->route('user.dashboard');
    }

    public function paymentCancel(Request $request)
    {
        $gateway = $request->query('gateway');
        Log::info("Payment cancelled", [
            'gateway' => $gateway,
            'data' => $request->all()
        ]);
        session()->flash('error', 'Your payment was cancelled. Please try again or contact support if you need assistance.');
        return redirect()->route('user.dashboard');
    }

    // Upgrade the package
    public function upgradePlan()
    {
        $user = Auth::user();

        if (!$user->hasRole(['User'])) {
            return redirect()->route('admin.dashboard');
        }

        $currentPackage = $user->package->name ?? null;
        $currentLoggedInUserPaymentGateway = optional($user->paymentGateway)->name ?? null;
        $userGateway = new PaymentGateways();
        $userGateway->name = $currentLoggedInUserPaymentGateway;
        $filteredGateways = collect([$userGateway]);
        $activeGateway = PaymentGateways::where('is_active', true)->first();
        $activeGatewaysByAdmin = PaymentGateways::where('is_active', true)
            ->whereNotNull('name')
            ->pluck('name')
            ->filter()
            ->values();
        $packages = Package::select('name', 'price', 'duration', 'features')->get();

        return view('subscription.index', [
            'payment_gateways' => $filteredGateways,
            'currentPackage' => $currentPackage,
            'activeGateway' => $activeGateway,
            'currentLoggedInUserPaymentGateway' => $currentLoggedInUserPaymentGateway,
            'activeGatewaysByAdmin' => $activeGatewaysByAdmin,
            'packages' => $packages,
        ]);
    }

    public function processUpgrade(Request $request, $packageName)
    {
        $user = Auth::user();
        if (!$user->hasRole(['User'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $package = Package::where('name', $packageName)->firstOrFail();
        if ($user->package && $user->package->name === $packageName) {
            return response()->json(['error' => 'You are already subscribed to this plan.'], 400);
        }
        if ($package->isFree()) {
            return response()->json(['error' => 'Cannot upgrade to a free plan.'], 400);
        }
        $paymentGateway = PaymentGateways::find($user->payment_gateway_id);
        if (!$paymentGateway) {
            Log::warning('No payment gateway found for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No payment gateway associated with your account.'], 400);
        }
        try {
            $this->paymentService->setGateway($paymentGateway->name);
            $result = $this->paymentService->createPaymentSession($packageName, $user);
            if ($result['success']) {
                return response()->json($result);
            } else {
                Log::error('Failed to create payment session for upgrade', ['error' => $result['error']]);
                return response()->json(['error' => $result['error'] ?? 'Failed to initiate payment.'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Upgrade payment error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while processing your upgrade.'], 500);
        }
    }

    protected function updateUserSubscription(Order $order)
    {
        $user = $order->user;
        $package = Package::where('name', $order->package)->firstOrFail();

        $startDate = Carbon::now();
        $endDate = null;

        if ($package->getMonthlyDuration() !== null) {
            $endDate = $startDate->copy()->addMonths($package->getMonthlyDuration());
        } elseif ($package->getDurationInDays() !== null) {
            $endDate = $startDate->copy()->addDays($package->getDurationInDays());
        }

        DB::transaction(function () use ($user, $package, $startDate, $endDate) {
            $user->update([
                'package_id' => $package->id,
                'subscription_starts_at' => $startDate,
                'subscription_ends_at' => $endDate,
                'is_subscribed' => 1,
            ]);
        });

        Log::info("User {$user->id} subscription upgraded to {$package->name}", [
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate ? $endDate->toDateTimeString() : null,
        ]);

        return true;
    }
}
