<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Payment\PaymentService;
use App\Services\SubscriptionService;
use App\Factories\PaymentGatewayFactory;
use App\Models\Package;
use App\Http\Requests\Payments\CheckoutRequest;
use App\Http\Requests\Payments\UpgradeRequest;
use App\Http\Requests\Payments\DowngradeRequest;
use App\Http\Requests\Payments\PayProGlobalDowngradeRequest;
use App\Http\Requests\Payments\CancelSubscriptionRequest;
use App\Http\Requests\Payments\SuccessCallbackRequest;
use App\Models\Order;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private SubscriptionService $subscriptionService,
        private PaymentGatewayFactory $paymentGatewayFactory,
    ) {}

    public function gatewayCheckout(CheckoutRequest $request, string $gateway, string $package)
    {
        Log::info('[gatewayCheckout] called', ['package' => $package, 'user_id' => Auth::id(), 'gateway' => $gateway]);

        try {
            $result = $this->paymentService->processPayment([
                'package' => $package,
                'user' => Auth::user(),
                'is_upgrade' => $request->input('is_upgrade', false),
                'is_downgrade' => $request->input('is_downgrade', false),
            ], $gateway, true);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Gateway checkout error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id(),
                'gateway' => $gateway
            ]);

            $statusCode = match(true) {
                str_contains($e->getMessage(), 'not authenticated') => 401,
                str_contains($e->getMessage(), 'not found') => 400,
                str_contains($e->getMessage(), 'restricted') => 403,
                str_contains($e->getMessage(), 'unavailable') => 409,
                default => 500
            };

            return response()->json([
                'error' => $this->getErrorMessage($e->getMessage()),
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function handleSuccess(SuccessCallbackRequest $request)
    {
        Log::info('[handleSuccess] called', [
            'auth_check_start' => auth()->check(),
            'auth_id_start' => auth()->id(),
            'params' => $request->all(),
            'query' => $request->query(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        try {
            // Handle PayProGlobal auth token authentication
            $authToken = $request->input('auth_token', $request->query('auth_token'));
            if ($authToken && !auth()->check()) {
                $userId = Cache::get("paypro_auth_token_{$authToken}");
                if ($userId) {
                    $user = \App\Models\User::find($userId);
                    if ($user && method_exists($user, 'hasRole') && $user->hasRole('User')) {
                        Auth::guard('web')->login($user, true);
                        $request->session()->save();
                        Log::info('[handleSuccess] User authenticated via auth token', [
                            'user_id' => $user->id,
                            'auth_token' => $authToken
                        ]);
                        // Clear the token after use
                        Cache::forget("paypro_auth_token_{$authToken}");
                    }
                }
            }

            $gateway = $request->input('gateway', $request->query('gateway'));

            if (empty($gateway) && $request->has('success-url')) {
                $successUrl = $request->input('success-url');
                $queryString = parse_url($successUrl, PHP_URL_QUERY);
                parse_str($queryString, $queryParams);
                $gateway = $queryParams['gateway'] ?? null;
            }

            if (empty($gateway)) {
                Log::error('No gateway specified in success callback', [
                    'params' => $request->all(),
                    'query' => $request->query(),
                    'url' => $request->fullUrl()
                ]);
                return redirect()->route('subscription')->with('error', 'Invalid payment gateway');
            }

            $result = $this->paymentService->processSuccessCallback($gateway, array_merge(
                $request->all(),
                $request->query(),
                ['transaction_id' => $request->query('transaction_id') ?? $request->input('transaction_id')]
            ));

            if (!isset($result['success'])) {
                Log::error('Invalid response from processSuccessCallback', [
                    'result' => $result,
                    'gateway' => $gateway
                ]);
                return redirect()->route('subscription')->with('error', 'Payment processing failed: Invalid response from payment service');
            }

            return $this->handleSuccessResponse($request, $gateway, $result);
        } catch (\Exception $e) {
            Log::error('Payment success callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_all' => $request->all()
            ]);
            return redirect()->route('subscription')->with('error', 'There was an error processing your payment: ' . $e->getMessage());
        }
    }

    private function handleSuccessResponse(Request $request, string $gateway, array $result)
    {
        if (!$result['success']) {
            return redirect()->route('subscription')->with('error', $result['error'] ?? 'Payment processing failed');
        }

        if (isset($result['already_completed'])) {
            if (!auth()->check()) {
                return redirect()->route('login')->with('info', 'Payment successful! Please log in to access your dashboard.');
            }
            return redirect()->route('user.dashboard')->with('success', 'Subscription active');
        }

        if ($gateway === 'payproglobal' && isset($result['user'])) {
            return $this->handlePayProGlobalSuccess($request, $result);
        }

        if (isset($result['user']) && isset($result['package'])) {
            $user = $result['user'];
            $package = $result['package'];
            $isUpgrade = $result['is_upgrade'] ?? false;
            $successMessage = $isUpgrade
                ? "Successfully upgraded to {$package->name}!"
                : "Successfully subscribed to {$package->name}!";

            if (!auth()->check()) {
                return redirect()->route('login')->with('info', $successMessage . ' Please log in to access your dashboard.');
            }
            return redirect()->route('user.dashboard')->with('success', $successMessage);
        }

        if (isset($result['package_name'])) {
            if (!auth()->check()) {
                return redirect()->route('login')->with('info', "Subscription to {$result['package_name']} bought successfully! Please log in to access your dashboard.");
            }
            return redirect()->route('user.subscription.details')->with('success', "Subscription to {$result['package_name']} bought successfully!");
        }

        return redirect()->route('user.dashboard')->with('success', 'Payment processed successfully');
    }

    private function handlePayProGlobalSuccess(Request $request, array $result)
    {
        $user = $result['user'];
        $successMessage = $result['message'] ?? 'Your subscription is now active!';

        // Ensure user is authenticated and session is saved
        if (!Auth::guard('web')->check() || Auth::guard('web')->id() !== $user->id) {
            if (method_exists($user, 'hasRole') && $user->hasRole('User')) {
                Auth::guard('web')->login($user, true);
                $request->session()->save();
                Log::info('[handlePayProGlobalSuccess] User logged in', ['user_id' => $user->id]);
            } else {
                return redirect()->route('login')->with('info', 'Payment processed successfully. Please log in to access your account.');
            }
        }

        // Clear any intended redirects
        $request->session()->forget('url.intended');
        $request->session()->forget('verification_intended_url');
        $request->session()->save();

        if ($request->query('popup') === 'true') {
            return view('payments.popup-close', ['message' => 'Payment successful! Please wait while we update your subscription.']);
        }

        // Always redirect to dashboard with success message
        return redirect()->route('user.dashboard')->with('success', $successMessage);
    }

    public function handleAddonSuccess(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return redirect()->route('login')->with('error', 'Please log in to complete your add-on purchase');
            }

            $orderId = $request->input('orderId');
            $addon = $request->input('addon');

            if (!$orderId || !$addon) {
                return redirect()->route('subscription')->with('error', 'Invalid add-on payment parameters');
            }

            $result = $this->paymentService->processAddonSuccess($user, $orderId, $addon);
            return redirect()->route('user.dashboard')->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error('Add-on success processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('subscription')->with('error', 'Failed to process add-on purchase');
        }
    }

    public function addonDebugLog(Request $request)
    {
        try {
            $message = $request->input('message', '');
            $context = $request->input('context', []);
            $level = strtolower($request->input('level', 'info'));

            $logPayload = array_merge([
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'user_agent' => $request->header('User-Agent'),
                'url' => $request->header('Referer'),
            ], is_array($context) ? $context : []);

            match($level) {
                'warning' => Log::warning('[AddonDebug] ' . $message, $logPayload),
                'error' => Log::error('[AddonDebug] ' . $message, $logPayload),
                default => Log::info('[AddonDebug] ' . $message, $logPayload)
            };

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('[AddonDebug] Failed to log', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    public function handlePaddleWebhook(Request $request)
    {
        Log::info('[handlePaddleWebhook] called', ['payload' => $request->all()]);
        try {
            $payload = $request->all();

            if (config('payment.gateways.Paddle.webhook_secret')) {
                $receivedSignature = $request->header('Paddle-Signature');
                $expectedSignature = hash_hmac('sha256', $request->getContent(), config('payment.gateways.Paddle.webhook_secret'));

                if (!hash_equals($expectedSignature, $receivedSignature)) {
                    Log::error('Invalid Paddle webhook signature');
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            $result = $this->paymentService->processWebhook('paddle', $payload);

            if (isset($result['error'])) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Paddle webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handleFastSpringWebhook(Request $request)
    {
        Log::info('[handleFastSpringWebhook] called', ['payload' => $request->all()]);
        try {
            $result = $this->paymentService->processWebhook('fastspring', $request->all());

            if ($result['status'] === 'processed' && isset($result['payment_data'])) {
                $this->paymentService->processPayment($result['payment_data'], 'fastspring', false);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('FastSpring webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            if ($e->getMessage() === 'license_api_failed') {
                return response()->json(['status' => 'failed_license_api'], 200);
            }

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        Log::info('PayProGlobal Webhook Client IP', ['ip' => $request->ip()]);

        $contentType = $request->header('Content-Type');
        $payload = [];

        if (str_contains($contentType, 'application/json')) {
            $payload = $request->json()->all();
        } else {
            parse_str($request->getContent(), $payload);
        }

        try {
            if (empty($payload)) {
                return response()->json(['success' => false, 'error' => 'Empty payload'], 400);
            }

            $result = $this->paymentService->processWebhook('payproglobal', $payload);

            if ($result['success'] && isset($result['order_id'])) {
                return response()->json($result, 200);
            }

            return response()->json($result, isset($result['error']) ? 500 : 200);
        } catch (\Exception $e) {
            Log::error('PayProGlobal webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($e->getMessage() === 'license_api_failed') {
                return response()->json(['success' => false, 'status' => 'failed_license_api'], 200);
            }

            return response()->json(['success' => false, 'error' => 'Processing failed'], 500);
        }
    }

    public function getOrdersList(Request $request)
    {
        Log::info('[getOrdersList] called', ['user_id' => Auth::id()]);
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
            ->where('status', '!=', 'Processing')
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function cancelSubscription(CancelSubscriptionRequest $request)
    {
        Log::info('[cancelSubscription] called', ['user_id' => Auth::id()]);

        try {
            $user = Auth::user();
            $result = $this->paymentService->handleSubscriptionCancellation(
                $user,
                $request->input('cancellation_reason_id'),
                $request->input('reason_text')
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->route('user.subscription.details')->with(
                $result['success'] ? 'success' : 'error',
                $result['message'] ?? ($result['success'] ? 'Subscription cancelled successfully' : 'Failed to cancel subscription')
            );
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $this->getErrorMessage($e->getMessage())
                ], 500);
            }

            return redirect()->route('user.subscription.details')->with('error', $this->getErrorMessage($e->getMessage()));
        }
    }

    public function upgradeToPackage(UpgradeRequest $request, string $package)
    {
        Log::info('[upgradeToPackage] called', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $user = Auth::user();
            $gateway = $this->paymentService->detectGatewayFromUser($user, $user->userLicence->subscription_id ?? null);

            if (!$gateway) {
                return response()->json([
                    'error' => 'Payment Method Not Found',
                    'message' => 'We couldn\'t determine your payment method. Please contact support to resolve this issue.',
                    'action' => 'contact_support'
                ], 400);
            }

            $result = $this->paymentService->handleUpgradeToPackage($user, $package, $gateway->name);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Package upgrade error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);

            $statusCode = match(true) {
                str_contains($e->getMessage(), 'not authenticated') => 401,
                str_contains($e->getMessage(), 'required') => 400,
                str_contains($e->getMessage(), 'restricted') => 403,
                str_contains($e->getMessage(), 'configuration') => 400,
                default => 500
            };

            return response()->json([
                'error' => $this->getErrorMessage($e->getMessage()),
                'message' => $e->getMessage(),
                'action' => 'retry_or_contact_support'
            ], $statusCode);
        }
    }

    public function verifyOrder(Request $request, string $transactionId)
    {
        Log::info('[verifyOrder] called', [
            'transaction_id' => $transactionId,
            'user_id' => Auth::id()
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $result = $this->paymentService->verifyOrderForUser($transactionId, $user->id);

            if (!$result['success']) {
                return response()->json($result, isset($result['status']) ? 200 : 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Order verification error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Verification failed'], 500);
        }
    }

    public function handleCancel(Request $request)
    {
        Log::info('[handleCancel] called', [
            'params' => $request->all(),
            'query' => $request->query(),
            'url' => $request->fullUrl()
        ]);

        return redirect()->route('subscription')->with('info', 'Payment was cancelled');
    }

    public function handlePopupCancel(Request $request)
    {
        return view('payments.popup-cancel');
    }

    public function handleLicenseError(Request $request)
    {
        return view('payments.license-error');
    }

    public function testPaddleConfiguration(Request $request)
    {
        try {
            $result = $this->paymentService->testPaddleConfiguration();
            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Paddle configuration test failed',
                'message' => $e->getMessage(),
                'config' => [
                    'api_key_exists' => !empty(config('payment.gateways.Paddle.api_key')),
                    'environment' => config('payment.gateways.Paddle.environment', 'sandbox')
                ]
            ], 500);
        }
    }

    public function payproglobalDowngrade(PayProGlobalDowngradeRequest $request)
    {
        Log::info('[payproglobalDowngrade] called', ['user_id' => Auth::id(), 'params' => $request->all()]);

        try {
            $user = Auth::user();
            $result = $this->paymentService->handlePayProGlobalDowngrade($user, $request->input('package_id'));

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('PayProGlobal downgrade checkout failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to initiate downgrade checkout.'], 500);
        }
    }

    public function downgradeSubscription(DowngradeRequest $request)
    {
        Log::info('=== PaymentController::downgradeSubscription CALLED ===', [
            'user_id' => Auth::id(),
            'user_authenticated' => Auth::check(),
            'request_data' => $request->all(),
            'package' => $request->input('package'),
            'headers' => $request->headers->all()
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $gateway = $this->paymentService->detectGatewayFromUser($user, $user->userLicence->subscription_id ?? null);

            if (!$gateway) {
                return response()->json([
                    'error' => 'Payment Method Not Found',
                    'message' => 'We couldn\'t determine your payment method. Please contact support to resolve this issue.',
                    'action' => 'contact_support'
                ], 400);
            }

            $gatewayName = $gateway->name;

            $targetPackageName = $request->input('package');
            $targetPackage = Package::whereRaw('LOWER(name) = ?', [strtolower($targetPackageName)])->first();

            if (!$targetPackage) {
                return response()->json([
                    'error' => 'Invalid package selected for downgrade',
                    'message' => 'Target package not found',
                ], 400);
            }

            $gatewayInstance = $this->paymentGatewayFactory
                ->create($gatewayName)
                ->setUser($user);

            if (!method_exists($gatewayInstance, 'handleDowngrade')) {
                return response()->json([
                    'error' => 'Downgrade Not Supported',
                    'message' => "Downgrade is not supported for gateway {$gatewayName}",
                ], 400);
            }

            return response()->json([
                'success' => false,
                'error' => 'Downgrade functionality is not available',
                'message' => 'Downgrade is not currently supported for this gateway.',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Package downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            $statusCode = match(true) {
                str_contains($e->getMessage(), 'not authenticated') => 401,
                str_contains($e->getMessage(), 'required') => 400,
                str_contains($e->getMessage(), 'restricted') => 403,
                str_contains($e->getMessage(), 'configuration') => 400,
                default => 500
            };

            return response()->json([
                'error' => $this->getErrorMessage($e->getMessage()),
                'message' => $e->getMessage(),
                'action' => 'retry_or_contact_support'
            ], $statusCode);
        }
    }

    private function getErrorMessage(string $message): string
    {
        return match(true) {
            str_contains($message, 'not authenticated') => 'User not authenticated',
            str_contains($message, 'not found') => 'Resource not found',
            str_contains($message, 'restricted') => 'Plan Change Restricted',
            str_contains($message, 'configuration') => 'License Configuration Issue',
            str_contains($message, 'unavailable') => 'Licenses temporarily unavailable',
            str_contains($message, 'required') => 'Subscription Required',
            default => 'Operation failed'
        };
    }
}
