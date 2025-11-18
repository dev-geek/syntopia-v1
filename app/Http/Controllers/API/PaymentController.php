<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Models\{Package, User, PaymentGateways, Order, UserLicence};
use App\Services\FastSpringClient;
use App\Services\LicenseService;
use App\Services\LicenseApiService;
use App\Services\PaddleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\SubscriptionService;
use App\Services\PayProGlobalClient;
use App\Services\DeviceFingerprintService;
use App\Services\FreePlanAbuseService;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private $licenseService;
    private $licenseApiService;
    private $subscriptionService;
    private $deviceFingerprintService;
    private $payProGlobalClient;
    private $freePlanAbuseService;

    public function __construct(
        LicenseService $licenseService,
        LicenseApiService $licenseApiService,
        SubscriptionService $subscriptionService,
        DeviceFingerprintService $deviceFingerprintService,
        PayProGlobalClient $payProGlobalClient,
        FreePlanAbuseService $freePlanAbuseService
    )
    {
        $this->licenseService = $licenseService;
        $this->licenseApiService = $licenseApiService;
        $this->subscriptionService = $subscriptionService;
        $this->deviceFingerprintService = $deviceFingerprintService;
        $this->payProGlobalClient = $payProGlobalClient;
        $this->freePlanAbuseService = $freePlanAbuseService;
    }

    private function isPrivilegedUser(?\App\Models\User $user): bool
    {
        try {
            if (!$user) {
                return false;
            }
            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole(['Super Admin', 'Sub Admin']);
            }
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('Super Admin') || $user->hasRole('Sub Admin');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }
    private function validatePackageAndGetUser($packageName)
    {
        $user = Auth::user();
        if (!$user) {
            Log::error('No authenticated user', ['package_name' => $packageName]);
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $package = ucfirst($packageName);

        $packageData = Package::where('name', $package)->first();
        if (!$packageData) {
            Log::error('Package not found', ['package_name' => $packageName]);
            return response()->json([
                'error' => 'Invalid Package',
                'message' => 'The selected package is not available or doesn\'t exist. Please choose a valid package.',
                'action' => 'select_valid_package'
            ], 400);
        }

        return ['user' => $user, 'packageData' => $packageData];
    }

    private function getPaymentGatewayId($gatewayName)
    {
        $gatewayMappings = [
            'paddle' => 'Paddle',
            'fastspring' => 'FastSpring',
            'payproglobal' => 'Pay Pro Global'
        ];

        $normalizedName = $gatewayMappings[strtolower($gatewayName)] ?? $gatewayName;
        return PaymentGateways::where('name', $normalizedName)->value('id');
    }

    private function processPackageName($package)
    {
        return str_replace('-plan', '', strtolower($package));
    }

    private function checkLicenseAvailability(string $plan = 'Free')
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        $resolved = $this->licenseApiService->resolvePlanLicense($tenantId, $plan, false);
        if (!$resolved) {
            Log::error('Requested plan not found in API inventory', [
                'plan' => $plan,
                'tenant_id' => $tenantId,
            ]);
            return false;
        }

        $remaining = (int)($resolved['remaining'] ?? 0);
        if ($remaining <= 0) {
            Log::error('No remaining licenses available for requested plan', [
                'plan' => $plan,
                'tenant_id' => $tenantId,
                'resolved' => $resolved,
            ]);
            return false;
        }

        Log::info('License availability check passed for plan', [
            'plan' => $plan,
            'subscription_name' => $resolved['subscriptionName'] ?? null,
            'subscription_code' => $resolved['subscriptionCode'] ?? null,
            'remaining' => $remaining,
        ]);
        return true;
    }

    /**
     * Standard response when licenses are unavailable across gateways.
     */
    private function licenseUnavailableResponse(Request $request)
    {
        $friendly = 'There is a technical issue with licenses for this plan. Please try purchasing later or contact support.';
        if (!$request->expectsJson()) {
            return redirect()->route('subscription')->with('error', $friendly);
        }
        return response()->json([
            'error' => 'Licenses temporarily unavailable',
            'message' => $friendly,
            'action' => 'retry_or_contact_support'
        ], 409);
    }

    /**
     * Handle free package assignment without payment gateway
     */
    private function handleFreePackageAssignment(Package $package, User $user, Request $request)
    {
        try {
            // Idempotency check: If user already has free plan, return success
            $user->refresh();
            if ($user->has_used_free_plan) {
                $hasCompletedOrder = DB::table('orders')
                    ->join('packages', 'orders.package_id', '=', 'packages.id')
                    ->where('orders.user_id', $user->id)
                    ->where('orders.status', 'completed')
                    ->where('packages.name', 'Free')
                    ->exists();

                if ($hasCompletedOrder) {
                    Log::info('Free plan already assigned to user (idempotency check)', [
                        'user_id' => $user->id,
                        'package_id' => $user->package_id
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Free plan is already active',
                        'redirect_url' => route('user.dashboard')
                    ]);
                }
            }

            // Check if user can use free plan (abuse prevention)
            $eligibilityCheck = $this->freePlanAbuseService->canUseFreePlan($user, $request);
            if (!$eligibilityCheck['allowed']) {
                return response()->json([
                    'error' => $eligibilityCheck['error_code'] ?? 'NOT_ALLOWED',
                    'message' => $eligibilityCheck['message'] ?? 'You are not allowed to use the free plan.',
                    'reason' => $eligibilityCheck['reason'] ?? 'not_allowed',
                    'action' => 'contact_support'
                ], 403);
            }

            DB::beginTransaction();

            if (!$this->checkLicenseAvailability($package->name)) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Licenses temporarily unavailable',
                    'message' => 'There is a technical issue with licenses for this plan. Please try again later or contact support.',
                    'action' => 'retry_or_contact_support'
                ], 409);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount' => 0,
                'currency' => 'USD',
                'transaction_id' => 'FREE-' . strtoupper(Str::random(10)),
                'status' => 'completed',
                'metadata' => [
                    'package' => $package->name,
                    'action' => 'free_plan_assignment'
                ]
            ]);

            $user->update([
                'package_id' => $package->id,
                'is_subscribed' => true,
                'has_used_free_plan' => true,
                'free_plan_used_at' => now()
            ]);

            // Record the free plan attempt for abuse tracking
            $this->freePlanAbuseService->recordAttempt($request, $user);

            // For free plans, license creation is optional
            // If user doesn't have tenant_id, skip license creation (it's not required for free plans)
            $license = null;
            if ($user->tenant_id) {
                $license = $this->licenseService->createAndActivateLicense(
                    $user,
                    $package,
                    'FREE-' . $user->id . '-' . time(),
                    null,
                    false
                );

                if (!$license) {
                    // Log warning but don't fail - free plans don't strictly require licenses
                    Log::warning('Failed to create license for free package, but continuing anyway', [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'reason' => 'License creation failed but free plan assignment continues'
                    ]);
                }
            } else {
                // User doesn't have tenant_id (e.g., created by admin and verified with "already registered" error)
                // This is fine for free plans - they don't require a license
                Log::info('Skipping license creation for free plan - user does not have tenant_id', [
                    'user_id' => $user->id,
                    'package_id' => $package->id
                ]);
            }

            DB::commit();

            Log::info('Free package assigned successfully', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'order_id' => $order->id,
                'license_id' => $license?->id,
                'has_license' => $license !== null,
                'has_tenant_id' => !empty($user->tenant_id)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Free plan activated successfully',
                'redirect_url' => route('user.dashboard')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign free package', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Assignment Failed',
                'message' => 'Failed to activate your free plan. Please try again or contact support.',
                'action' => 'retry_or_contact_support'
            ], 500);
        }
    }



    public function paddleCheckout(Request $request, string $package)
    {
        Log::info('[paddleCheckout] called', ['package' => $package, 'user_id' => Auth::id()]);
        Log::info('Paddle checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            if ($packageData->isFree()) {
                Log::info('Free package detected in paddleCheckout, assigning directly', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return $this->handleFreePackageAssignment($packageData, $user, $request);
            }

        } catch (\Exception $e) {
            Log::error('Error in paddleCheckout before payment gateway logic', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }

        // Log Paddle configuration for debugging
        Log::info('Paddle configuration', [
            'api_key_exists' => !empty(config('payment.gateways.Paddle.api_key')),
            'environment' => config('payment.gateways.Paddle.environment', 'sandbox'),
            'api_url' => config('payment.gateways.Paddle.api_url')
        ]);

        try {
            // Block checkout if no licenses available for selected plan
            if (!$this->checkLicenseAvailability($packageData->name)) {
                return $this->licenseUnavailableResponse($request);
            }

            // Block further plan changes if an upgrade is currently active (except admin roles)
            if (!($this->isPrivilegedUser($user)) && !$this->licenseService->canUserChangePlan($user)) {
                return response()->json([
                    'error' => 'Plan Change Restricted',
                    'message' => 'You already have an active upgraded plan. Further upgrades or changes are not allowed until this plan expires.',
                    'action' => 'info'
                ], 403);
            }

            $isUpgrade = $request->input('is_upgrade', false);
            $isDowngrade = $request->input('is_downgrade', false);

            // Auto-detect upgrade if user has an active subscription and is trying to get a different package
            if (!$isUpgrade && $user->is_subscribed && $user->package_id && $user->package_id !== $packageData->id) {
                $isUpgrade = true;
                Log::info('Auto-detected upgrade based on user subscription status', [
                    'user_id' => $user->id,
                    'current_package_id' => $user->package_id,
                    'new_package_id' => $packageData->id,
                    'current_package_name' => $user->package->name ?? 'Unknown',
                    'new_package_name' => $packageData->name
                ]);
            }

            // For upgrades and downgrades, we should handle them directly in paddleCheckout
            if ($isUpgrade || $isDowngrade) {
                Log::info('Handling upgrade/downgrade directly in paddleCheckout', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'is_upgrade' => $isUpgrade,
                    'is_downgrade' => $isDowngrade
                ]);

                // For upgrades, create the upgrade checkout directly
                if ($isUpgrade) {
                    // Get the current subscription ID from the user's license
                    $currentLicense = $user->userLicence;
                    if (!$currentLicense || !$currentLicense->subscription_id) {
                        return response()->json([
                            'error' => 'License Configuration Issue',
                            'message' => 'Your software license is not properly configured for upgrades. Please contact support.',
                            'action' => 'contact_support'
                        ], 400);
                    }

                    $subscriptionId = $currentLicense->subscription_id;

                    // Get the price ID for the new package
                    $productsResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('payment.gateways.Paddle.api_key'),
                        'Content-Type' => 'application/json'
                    ])->get((config('payment.gateways.Paddle.environment', 'sandbox') === 'production' ? 'https://api.paddle.com' : 'https://sandbox-api.paddle.com') . "/products", ['include' => 'prices']);

                    if (!$productsResponse->successful()) {
                        return response()->json([
                            'error' => 'Product Information Unavailable',
                            'message' => 'We\'re unable to retrieve product information at the moment. Please try again later.',
                            'action' => 'retry'
                        ], 500);
                    }

                    $products = $productsResponse->json()['data'];
                    $matchingProduct = collect($products)->firstWhere('name', $packageData->name);

                    if (!$matchingProduct) {
                        return response()->json(['error' => 'Unavailable package'], 400);
                    }

                    $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
                    if (!$price) {
                        return response()->json(['error' => 'No active price'], 400);
                    }

            // Block if no licenses available before upgrade checkout
            if (!$this->checkLicenseAvailability($packageData->name)) {
                return $this->licenseUnavailableResponse($request);
            }

            // Create the upgrade checkout
                    $checkoutUrl = $this->createPaddleUpgradeCheckout($user, $packageData, $subscriptionId, $price['id']);

                    if (!$checkoutUrl) {
                        return response()->json(['error' => 'Failed to create upgrade checkout'], 500);
                    }

                    // Extract transaction ID from the checkout URL or generate one
                    $transactionId = null;
                    if (preg_match('/_ptxn=([^&]+)/', $checkoutUrl, $matches)) {
                        $transactionId = $matches[1];
                    } else {
                        // Fallback: generate a transaction ID based on the order
                        $order = Order::where('user_id', $user->id)
                            ->where('package_id', $packageData->id)
                            ->latest()
                            ->first();
                        $transactionId = $order ? $order->transaction_id : 'PADDLE-UPGRADE-' . Str::random(10);
                    }

                    return response()->json([
                        'success' => true,
                        'checkout_url' => $checkoutUrl,
                        'transaction_id' => $transactionId,
                        'message' => 'Upgrade checkout created successfully'
                    ]);
                } else {
                    // Handle downgrade similarly
                    return response()->json(['error' => 'Downgrade not implemented yet'], 400);
                }
            } else {
            // Do NOT create license before payment. Allocate after success/thank-you.
            $license = null;
            Log::info('Deferring license creation until payment success (Paddle)', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'is_upgrade' => $isUpgrade
            ]);
            }

            // Create a pending order to track this checkout attempt
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'transaction_id' => 'PADDLE-PENDING-' . Str::random(10),
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'status' => 'pending',
                'metadata' => [
                    // no license id pre-payment
                    'is_upgrade' => $isUpgrade,
                    'is_downgrade' => $isDowngrade
                ]
            ]);

            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing', [
                    'environment' => $environment,
                    'api_key_length' => strlen($apiKey ?? '')
                ]);
                return response()->json([
                    'error' => 'Payment configuration error',
                    'details' => 'Paddle API key is not configured'
                ], 500);
            }

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];

            if (!$user->paddle_customer_id) {
                Log::info('Checking for existing Paddle customer', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                // First, try to find existing customer by email
                $existingCustomerResponse = Http::withHeaders($headers)
                    ->get("{$apiBaseUrl}/customers", ['email' => $user->email]);

                if ($existingCustomerResponse->successful()) {
                    $customers = $existingCustomerResponse->json()['data'] ?? [];
                    if (!empty($customers)) {
                        $existingCustomer = $customers[0]; // Take the first matching customer
                        $user->update(['paddle_customer_id' => $existingCustomer['id']]);

                        Log::info('Found existing Paddle customer', [
                            'user_id' => $user->id,
                            'paddle_customer_id' => $existingCustomer['id']
                        ]);
                    }
                }

                // If no existing customer found, create a new one
                if (!$user->paddle_customer_id) {
                    Log::info('Creating new Paddle customer', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name
                    ]);

                    $customerData = [
                        'email' => $user->email,
                        'name' => $user->name ?: ($user->first_name && $user->last_name ? $user->first_name . ' ' . $user->last_name : 'User'),
                        'custom_data' => ['user_id' => (string) $user->id]
                    ];

                    // Ensure name is not empty
                    if (empty($customerData['name']) || trim($customerData['name']) === '') {
                        $customerData['name'] = 'User';
                    }

                    Log::info('Paddle customer creation request', [
                        'url' => "{$apiBaseUrl}/customers",
                        'data' => $customerData
                    ]);

                    $customerResponse = Http::withHeaders($headers)->post("{$apiBaseUrl}/customers", $customerData);

                    Log::info('Paddle customer creation response', [
                        'status' => $customerResponse->status(),
                        'body' => $customerResponse->body()
                    ]);

                    if (!$customerResponse->successful()) {
                        $responseData = $customerResponse->json();

                        // Check if customer already exists
                        if (
                            $customerResponse->status() === 409 &&
                            isset($responseData['error']['code']) &&
                            $responseData['error']['code'] === 'customer_already_exists'
                        ) {

                            Log::info('Paddle customer already exists, extracting customer ID', [
                                'user_id' => $user->id,
                                'response' => $responseData
                            ]);

                            // Extract customer ID from the error message
                            $customerId = null;
                            if (isset($responseData['error']['detail'])) {
                                // The detail contains: "customer email conflicts with customer of id ctm_01k0q9qrqyxr4g23cy6y0921wg"
                                if (preg_match('/customer of id ([a-zA-Z0-9_]+)/', $responseData['error']['detail'], $matches)) {
                                    $customerId = $matches[1];
                                }
                            }

                            if ($customerId) {
                                // Save the existing customer ID
                                $user->update(['paddle_customer_id' => $customerId]);

                                Log::info('Paddle customer ID saved from existing customer', [
                                    'user_id' => $user->id,
                                    'paddle_customer_id' => $customerId
                                ]);
                            } else {
                                Log::error('Could not extract customer ID from Paddle response', [
                                    'user_id' => $user->id,
                                    'response' => $responseData
                                ]);
                                return response()->json([
                                    'error' => 'Customer setup failed',
                                    'details' => 'Could not retrieve existing customer information'
                                ], 500);
                            }
                        } else {
                            $statusCode = $customerResponse->status();
                            Log::error('Paddle customer creation failed', [
                                'user_id' => $user->id,
                                'status' => $statusCode,
                                'response' => $customerResponse->body(),
                                'request_data' => $customerData
                            ]);

                            // Provide a user-friendly error message (hide raw details)
                            $friendlyMessage = 'We could not start your checkout with server issue right now. Please try again in a moment. If this keeps happening, contact support.';
                            if ($statusCode === 403) {
                                $friendlyMessage = 'We are currently unable to connect to server for your checkout. Please try again shortly.';
                            }

                            return response()->json([
                                'error' => 'We are currently unable to connect to server for your checkout. Please try again shortly.',
                                'message' => $friendlyMessage,
                                'action' => 'retry_or_contact_support'
                            ], 502);
                        }
                    } else {
                        // Customer was created successfully
                        $customerData = $customerResponse->json();
                        if (!isset($customerData['data']['id'])) {
                            Log::error('Paddle customer creation response missing customer ID', [
                                'response' => $customerData
                            ]);
                            return response()->json(['error' => 'Customer setup failed - invalid response'], 500);
                        }

                        $user->update(['paddle_customer_id' => $customerData['data']['id']]);

                        Log::info('Paddle customer created successfully', [
                            'user_id' => $user->id,
                            'paddle_customer_id' => $user->paddle_customer_id
                        ]);
                    }
                }
            }

            $productsResponse = Http::withHeaders($headers)
                ->get("{$apiBaseUrl}/products", ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Paddle products fetch failed', ['status' => $productsResponse->status()]);
                return response()->json(['error' => 'Product fetch failed'], 500);
            }

            $products = $productsResponse->json()['data'];
            Log::info('Available Paddle products', [
                'products' => collect($products)->pluck('name')->toArray(),
                'searching_for' => $package
            ]);

            $matchingProduct = collect($products)->firstWhere('name', $package);

            if (!$matchingProduct) {
                Log::error('Paddle product not found', [
                    'package' => $package,
                    'available_products' => collect($products)->pluck('name')->toArray()
                ]);
                return response()->json([
                    'error' => 'Unavailable package',
                    'details' => "Package '{$package}' not found in Paddle products"
                ], 400);
            }

            $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
            if (!$price) {
                Log::error('No active prices found', ['product_id' => $matchingProduct['id']]);
                return response()->json(['error' => 'No active price'], 400);
            }

            $transactionData = [
                'items' => [['price_id' => $price['id'], 'quantity' => 1]],
                'customer_id' => $user->paddle_customer_id,
                'currency_code' => 'USD',
                'custom_data' => [
                    'user_id' => (string) $user->id,
                    'package_id' => (string) $packageData->id,
                    'package' => $package,
                    'order_id' => (string) $order->id,
                    'license_id' => $license ? (string) $license->id : null
                ],
                'checkout' => [
                    'settings' => ['display_mode' => 'overlay'],
                    'success_url' => route('payments.success', ['gateway' => 'paddle', 'transaction_id' => '{transaction_id}']),
                    'cancel_url' => route('payments.popup-cancel')
                ]
            ];

            $response = Http::withHeaders($headers)
                ->post("{$apiBaseUrl}/transactions", $transactionData);

            if (!$response->successful()) {
                Log::error('Paddle transaction creation failed', ['status' => $response->status(), 'response' => $response->body()]);
                return response()->json(['error' => 'Transaction creation failed'], $response->status());
            }

            $transaction = $response->json()['data'];

            // Update the order with the real transaction ID
            $order->update([
                'transaction_id' => $transaction['id'],
                'metadata' => array_merge($order->metadata ?? [], [
                    'paddle_transaction_id' => $transaction['id'],
                    'checkout_url' => $transaction['checkout']['url']
                ])
            ]);

            Log::info('Paddle checkout created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction['id'],
                'checkout_url' => $transaction['checkout']['url'],
                'order_id' => $order->id
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $transaction['checkout']['url'],
                'transaction_id' => $transaction['id'],
                'order_id' => $order->id
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);

            // If we created a license and the checkout failed, we should clean it up
            if (isset($license) && $license) {
                try {
                    Log::info('Cleaning up license due to checkout failure', [
                        'user_id' => Auth::id(),
                        'license_id' => $license->id,
                        'package' => $package
                    ]);

                    // Delete the license record
                    $license->delete();

                    Log::info('License cleanup completed', [
                        'user_id' => Auth::id(),
                        'license_id' => $license->id
                    ]);
                } catch (\Exception $cleanupError) {
                    Log::error('Failed to cleanup license after checkout failure', [
                        'user_id' => Auth::id(),
                        'license_id' => $license->id ?? null,
                        'cleanup_error' => $cleanupError->getMessage()
                    ]);
                }
            }

            // If we created an order and the checkout failed, mark it as failed
            if (isset($order) && $order) {
                try {
                    $order->update([
                        'status' => 'failed',
                        'metadata' => array_merge($order->metadata ?? [], [
                            'error' => $e->getMessage(),
                            'failed_at' => now()->toISOString()
                        ])
                    ]);

                    Log::info('Order marked as failed due to checkout error', [
                        'user_id' => Auth::id(),
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                } catch (\Exception $orderError) {
                    Log::error('Failed to update order status after checkout failure', [
                        'user_id' => Auth::id(),
                        'order_id' => $order->id ?? null,
                        'order_error' => $orderError->getMessage()
                    ]);
                }
            }

            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function fastspringCheckout(Request $request, $package)
    {
        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            if ($packageData->isFree()) {
                Log::info('Free package detected in fastspringCheckout, assigning directly', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return $this->handleFreePackageAssignment($packageData, $user, $request);
            }

            if (!$this->checkLicenseAvailability($packageData->name)) {
                return $this->licenseUnavailableResponse($request);
            }

            if (!($this->isPrivilegedUser($user)) && !$this->licenseService->canUserChangePlan($user)) {
                return response()->json([
                    'error' => 'Plan Change Restricted',
                    'message' => 'You already have an active upgraded plan. Further upgrades or changes are not allowed until this plan expires.',
                    'action' => 'info'
                ], 403);
            }

            $isUpgrade = $request->input('is_upgrade', false);
            $isDowngrade = $request->input('is_downgrade', false);

            if (!$this->checkLicenseAvailability($packageData->name)) {
                $friendly = 'There is a technical issue with licenses for this plan. Please try purchasing later or contact support.';
                if (!$request->expectsJson()) {
                    return redirect()->route('subscription')->with('error', $friendly);
                }
                return response()->json([
                    'error' => 'Licenses temporarily unavailable',
                    'message' => $friendly,
                    'action' => 'retry_or_contact_support'
                ], 409);
            }

            $license = null;
            Log::info('Deferring license creation until payment success (FastSpring)', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'is_upgrade' => $isUpgrade
            ]);

            $storefront = config('payment.gateways.FastSpring.storefront');
            if (!$storefront) {
                Log::error('FastSpring storefront not configured');
                return response()->json(['error' => 'FastSpring configuration error'], 500);
            }

            $secureHash = hash_hmac(
                'sha256',
                $user->id . $processedPackage . time(),
                config('payment.gateways.FastSpring.webhook_secret')
            );
            $queryParams = [
                'referrer' => $user->id,
                'contactEmail' => $user->email,
                'contactFirstName' => $user->first_name ?? '',
                'contactLastName' => $user->last_name ?? '',
                'tags' => json_encode([
                    'user_id' => $user->id,
                    'package' => $packageData->name,
                    'package_id' => $packageData->id,
                    'secure_hash' => $secureHash,
                    'action' => $isUpgrade ? 'upgrade' : 'new'
                ]),
                'mode' => 'popup',
                'successUrl' => route('payments.success', [
                    'gateway' => 'fastspring',
                    'orderId' => '{orderReference}',
                    'popup' => 'true',
                    'package_name' => $processedPackage,
                    'payment_gateway_id' => $this->getPaymentGatewayId('fastspring')
                ]),
                'cancelUrl' => route('payments.popup-cancel')
            ];

            if ($isUpgrade && $user->subscription_id) {
                $queryParams['subscription_id'] = $user->subscription_id;
            }

            $checkoutUrl = "https://{$storefront}/{$processedPackage}?" . http_build_query($queryParams);

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'transaction_id' => 'FS-PENDING-' . Str::random(10),
                'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                'status' => 'pending',
                'metadata' => ['checkout_url' => $checkoutUrl]
            ]);

            Log::info('FastSpring checkout created', [
                'user_id' => $user->id,
                'transaction_id' => $order->transaction_id,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl
            ]);
        } catch (\Exception $e) {
            Log::error('FastSpring checkout error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function payProGlobalCheckout(Request $request, string $package)
    {
        Log::info('PayProGlobal checkout started', [
            'package' => $package,
            'user_id' => Auth::id(),
            'is_upgrade' => $request->input('is_upgrade', false),
            'is_downgrade' => $request->input('is_downgrade', false)
        ]); //dd($request->all(), $package);

        Log::info('Auth::user() status in payProGlobalCheckout', [
            'authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'csrf_token_header' => $request->header('X-CSRF-TOKEN'),
            'cookie_header' => $request->header('Cookie'),
        ]);

        try {
            $processedPackage = $this->processPackageName($package);
            $user = Auth::user();

            if (!$user) {
                Log::error('User not authenticated for PayProGlobal checkout');
                return response()->json([
                    'error' => 'Authentication Required',
                    'message' => 'Please log in to continue with your purchase.',
                    'action' => 'login'
                ], 401);
            }

            $packageData = Package::whereRaw('LOWER(name) = ?', [$processedPackage])->first();
            if (!$packageData) {
                Log::error('Package not found', ['package' => $processedPackage]);
                return response()->json([
                    'error' => 'Invalid Package',
                    'message' => 'The selected package is not available or doesn\'t exist. Please choose a valid package.',
                    'action' => 'select_valid_package'
                ], 400);
            }

            if ($packageData->isFree()) {
                Log::info('Free package detected in payProGlobalCheckout, assigning directly', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);

                // Check if user already has free plan - return success if they do (idempotency)
                $user->refresh();
                if ($user->has_used_free_plan || ($user->package_id && $user->package && $user->package->isFree())) {
                    Log::info('User already has free plan, returning success (idempotency)', [
                        'user_id' => $user->id,
                        'has_used_free_plan' => $user->has_used_free_plan,
                        'package_id' => $user->package_id
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Free plan is already active',
                        'redirect_url' => route('user.dashboard')
                    ]);
                }

                return $this->handleFreePackageAssignment($packageData, $user, $request);
            }

            $isUpgrade = $request->input('is_upgrade', false);
            $isDowngrade = $request->input('is_downgrade', false);

            if (!($this->isPrivilegedUser($user)) && !$this->licenseService->canUserChangePlan($user)) {
                return response()->json([
                    'error' => 'Plan Change Restricted',
                    'message' => 'You already have an active upgraded plan. Further upgrades or changes are not allowed until this plan expires.',
                    'action' => 'info'
                ], 403);
            }

            if ($isDowngrade) {
                return $this->payproglobalDowngrade($request, $packageData);
            }

            if (!$this->checkLicenseAvailability($packageData->name)) {
                Log::warning('Checkout blocked due to no available licenses', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return response()->json([
                    'error' => 'We\'re experiencing a temporary issue processing your license. Please try again in a few moments, or contact our support team for assistance.',
                    'message' => 'There is a technical issue with licenses for this plan. Please try purchasing later or contact support.',
                    'action' => 'retry_or_contact_support'
                ], 409);
            }

            Log::info('Deferring license creation until payment success (PayPro Global)', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'is_upgrade' => $request->input('is_upgrade', false),
                'is_downgrade' => $request->input('is_downgrade', false)
            ]);

            if ($isDowngrade) {
                $currentLicense = $user->userLicence;
                if (!$currentLicense || !$currentLicense->subscription_id) {
                    Log::error('User does not have an active subscription for downgrade', ['user_id' => $user->id]);
                    return response()->json([
                        'error' => 'No Active Subscription',
                        'message' => 'You do not have an active subscription to downgrade.',
                        'action' => 'info'
                    ], 400);
                }

                $subscriptionId = $currentLicense->subscription_id;
                $newProductId = config("payment.gateways.PayProGlobal.product_ids.{$processedPackage}");

                if (!$newProductId) {
                    Log::error('PayProGlobal product ID not configured for downgrade', ['package' => $processedPackage]);
                    return response()->json([
                        'error' => 'Product Not Available',
                        'message' => 'This product is currently not available for downgrade. Please try again later or contact support.',
                        'action' => 'contact_support'
                    ], 400);
                }

                Log::info('Initiating PayProGlobal downgrade API call', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'new_product_id' => $newProductId
                ]);

                $downgradeResponse = $this->payProGlobalClient->downgradeSubscription($subscriptionId, $newProductId);

                if (isset($downgradeResponse['result']) && $downgradeResponse['result'] === 'OK') {
                    Log::info('PayProGlobal downgrade successful', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'new_package' => $packageData->name,
                        'response' => $downgradeResponse
                    ]);
                    $user->update([
                        'package_id' => $packageData->id,
                        'is_subscribed' => true
                    ]);

                    $currentLicense->update([
                        'package_id' => $packageData->id,
                        'expires_at' => $packageData->isFree() ? null : now()->addMonth(),
                        'is_upgrade_license' => false
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Your subscription has been successfully downgraded.',
                        'redirect_url' => route('user.subscription.details')
                    ]);
                } else {
                    Log::error('PayProGlobal downgrade failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'new_product_id' => $newProductId,
                        'response' => $downgradeResponse
                    ]);
                    return response()->json([
                        'error' => 'Downgrade Failed',
                        'message' => $downgradeResponse['message'] ?? 'Failed to downgrade your subscription. Please try again or contact support.'
                    ], 500);
                }
                return;
            }

            $productId = config("payment.gateways.PayProGlobal.product_ids.{$processedPackage}");
            if (!$productId) {
                Log::error('PayProGlobal product ID not configured', ['package' => $processedPackage]);
                return response()->json([
                    'error' => 'Product Not Available',
                    'message' => 'This product is currently not available for purchase. Please try again later or contact support.',
                    'action' => 'contact_support'
                ], 400);
            }

            $pendingOrderId = 'PPG-PENDING-' . Str::random(10);
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'transaction_id' => $pendingOrderId,
                'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                'status' => 'pending',
                'metadata' => [
                    'package' => $processedPackage,
                    'pending_order_id' => $pendingOrderId,
                    'action' => $request->input('is_upgrade') ? 'upgrade' : ($request->input('is_downgrade') ? 'downgrade' : 'new')
                ]
            ]);

            $successParams = [
                'gateway' => 'payproglobal',
                'user_id' => $user->id,
                'package' => $processedPackage,
                'popup' => 'true',
                'pending_order_id' => $order->transaction_id,
                'action' => $request->input('is_upgrade') ? 'upgrade' : ($request->input('is_downgrade') ? 'downgrade' : 'new')
            ];

            // Use production URL in production, otherwise use current request domain
            if (app()->environment('production')) {
                $baseUrl = 'https://app.syntopia.ai';
            } else {
                $baseUrl = $request->getSchemeAndHttpHost() ?: config('app.url');
            }
            $successUrl = $baseUrl . route('payments.success', $successParams, false);

            $checkoutParams = [
                'products[1][id]' => $productId,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'custom' => json_encode([
                    'user_id' => $user->id,
                    'package_id' => $packageData->id,
                    'package' => $processedPackage,
                    'pending_order_id' => $order->transaction_id,
                    'action' => $request->input('is_upgrade') ? 'upgrade' : ($request->input('is_downgrade') ? 'downgrade' : 'new')
                ]),
                'page-template' => 'ID',
                'currency' => 'USD',
                'use-test-mode' => config('payment.gateways.PayProGlobal.test_mode', true) ? 'true' : 'false',
                'secret-key' => config('payment.gateways.PayProGlobal.webhook_secret'),
                'success-url' => $successUrl,
                'cancel-url' => $baseUrl . route('payments.popup-cancel', [], false)
            ];

            $checkoutUrl = "https://store.payproglobal.com/checkout?" . http_build_query($checkoutParams);

            Log::info('PayProGlobal checkout created', [
                'user_id' => $user->id,
                'pending_order_id' => $pendingOrderId,
                'success_url' => $successUrl,
                'success_url_route' => route('payments.success', $successParams, false),
                'base_url' => $baseUrl,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'pending_order_id' => $pendingOrderId
            ]);
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }
    }

    private function getSubscriptionId($orderId)
    {
        $response = Http::withBasicAuth(
            config('payment.gateways.FastSpring.username'),
            config('payment.gateways.FastSpring.password')
        )->get("https://api.fastspring.com/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('FastSpring order verification failed', [
                'order_id' => $orderId,
                'response' => $response->body(),
            ]);
            return ['error' => 'Order verification failed.'];
        }

        $order = $response->json();
        $subscriptionId = $order['items'][0]['subscription'] ?? null;

        if (!$subscriptionId) {
            return ['error' => 'No subscription found for this order.'];
        }

        $subscriptionResponse = Http::withBasicAuth(
            config('payment.gateways.FastSpring.username'),
            config('payment.gateways.FastSpring.password')
        )->get("https://api.fastspring.com/subscriptions/{$subscriptionId}");

        if ($subscriptionResponse->failed()) {
            Log::error('FastSpring subscription verification failed', [
                'subscription_id' => $subscriptionId,
                'response' => $subscriptionResponse->body(),
            ]);
            return ['error' => 'Subscription verification failed.'];
        }

        return $subscriptionResponse->json();
    }

    public function handleSuccess(Request $request)
    {
        Log::info('[handleSuccess] called', [
            'auth_check_start' => auth()->check(),
            'auth_id_start' => auth()->id(),
            'params' => $request->all(),
            'query' => $request->query(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->header('Referer')
        ]);
        Log::info('=== PAYMENT SUCCESS CALLBACK STARTED ===', [
            'params' => $request->all(),
            'query' => $request->query(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->header('Referer')
        ]);

        try {
            $gateway = $request->input('gateway', $request->query('gateway'));

            // For PayProGlobal, the gateway might be embedded in the success-url parameter
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

            if ($gateway === 'paddle') {
                Log::info('=== PROCESSING PADDLE SUCCESS CALLBACK ===', [
                    'gateway' => $gateway,
                    'all_params' => $request->all(),
                    'query_params' => $request->query(),
                    'input_params' => $request->input()
                ]);

                $transactionId = $request->query('transaction_id') ?? $request->input('transaction_id');
                Log::info('Extracted transaction ID from request', [
                    'transaction_id' => $transactionId,
                    'from_query' => $request->query('transaction_id'),
                    'from_input' => $request->input('transaction_id')
                ]);

                if (!$transactionId) {
                    Log::error('Missing Paddle transaction_id', [
                        'params' => $request->all(),
                        'auth_check_before_redirect' => auth()->check(),
                        'auth_id_before_redirect' => auth()->id()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Invalid payment request');
                }

                Log::info('Processing Paddle success callback', [
                    'transaction_id' => $transactionId,
                    'user_authenticated' => Auth::check(),
                    'user_id' => Auth::id()
                ]);

                $order = Order::where('transaction_id', $transactionId)->first();

                // If order not found, look for orders with temporary transaction IDs
                if (!$order) {
                    Log::info('Order not found with transaction_id, looking for temporary transaction IDs', [
                        'transaction_id' => $transactionId
                    ]);

                    // Look for pending orders that might be related to this transaction
                    // Since orders table doesn't have metadata, we'll look for pending orders by user
                    $pendingOrders = Order::where('status', 'pending')
                        ->get();

                    foreach ($pendingOrders as $pendingOrder) {
                        // Check if this order might be related to the current transaction
                        // We'll use a simple approach - if no order found by transaction_id,
                        // we'll process the payment using the webhook method
                        Log::info('Found pending order', [
                            'order_id' => $pendingOrder->id,
                            'transaction_id' => $pendingOrder->transaction_id,
                            'user_id' => $pendingOrder->user_id
                        ]);
                    }
                }

                if ($order && $order->status === 'completed') {
                    Log::info('Order already completed', ['transaction_id' => $transactionId]);
                    Log::info('Redirect decision for completed Paddle order', [
                        'auth_check' => auth()->check(),
                        'auth_id' => auth()->id()
                    ]);
                    if (!auth()->check()) {
                        return redirect()->route('login')->with('info', 'Payment successful! Please log in to access your dashboard.');
                    }
                    return redirect()->route('user.dashboard')->with('success', 'Subscription active');
                }

                // If we found an order, we can update it if needed
                if ($order) {
                    Log::info('Found existing order for transaction', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId
                    ]);
                }

                $apiKey = config('payment.gateways.Paddle.api_key');
                $environment = config('payment.gateways.Paddle.environment', 'sandbox');
                $apiBaseUrl = $environment === 'production'
                    ? 'https://api.paddle.com'
                    : 'https://sandbox-api.paddle.com';

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])->get("{$apiBaseUrl}/transactions/{$transactionId}");

                if (!$response->successful() || !in_array($response->json()['data']['status'], ['completed', 'paid'])) {
                    Log::error('Paddle transaction verification failed', [
                        'transaction_id' => $transactionId,
                        'response' => $response->body()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Payment verification failed');
                }

                $transactionData = $response->json()['data'];
                $customData = $transactionData['custom_data'] ?? [];
                $userId = $customData['user_id'] ?? null;

                if (!$userId) {
                    Log::error('No user_id in Paddle transaction custom_data', [
                        'transaction_id' => $transactionId,
                        'custom_data' => $customData
                    ]);
                    return redirect()->route('subscription')->with('error', 'Invalid transaction data');
                }

                Log::info('Processing Paddle payment with user_id from custom_data', [
                    'transaction_id' => $transactionId,
                    'user_id' => $userId,
                    'custom_data' => $customData
                ]);

                // Check if this is an upgrade/downgrade transaction
                Log::info('Querying for pending order', [
                    'transaction_id' => $transactionId,
                    'transaction_id_type' => gettype($transactionId),
                    'transaction_id_empty' => empty($transactionId)
                ]);

                $pendingOrder = Order::where('transaction_id', $transactionId)
                    ->whereIn('status', ['pending', 'pending_upgrade'])
                    ->first();

                if ($pendingOrder) {
                    Log::info('Processing Paddle pending order in success callback', [
                        'transaction_id' => $transactionId,
                        'order_id' => $pendingOrder->id,
                        'user_id' => $userId,
                        'order_status' => $pendingOrder->status,
                        'order_type' => $pendingOrder->order_type
                    ]);

                    // Update the order status
                    $pendingOrder->update(['status' => 'completed']);

                    // Get the user and package
                    $user = User::find($userId);
                    $package = Package::find($pendingOrder->package_id);

                    if ($user && $package) {
                        // Get subscription_id from transaction data if available
                        $subscriptionId = $transactionData['subscription_id'] ?? ($pendingOrder->metadata['subscription_id'] ?? null) ?? null;

                        // For Paddle, if no subscription_id is available, generate one based on transaction
                        if (!$subscriptionId) {
                            $subscriptionId = 'PADDLE-' . $transactionId;
                            Log::info('Generated subscription_id for Paddle order', [
                                'user_id' => $userId,
                                'transaction_id' => $transactionId,
                                'generated_subscription_id' => $subscriptionId
                            ]);
                        }

                        // Update user's package, subscription status, and payment gateway
                        $user->update([
                            'package_id' => $package->id,
                            'is_subscribed' => true,
                            'payment_gateway_id' => $pendingOrder->payment_gateway_id,
                            'subscription_id' => $subscriptionId
                        ]);

                        // Create or update license
                        $license = $this->licenseService->createAndActivateLicense(
                            $user,
                            $package,
                            $subscriptionId,
                            $pendingOrder->payment_gateway_id
                        );

                        if ($license) {
                            // Check if this is an upgrade transaction
                            $isUpgrade = ($customData['action'] ?? '') === 'upgrade' || $pendingOrder->order_type === 'upgrade';

                            Log::info('License created successfully for Paddle order in success callback', [
                                'user_id' => $userId,
                                'package' => $package->name,
                                'license_id' => $license->id,
                                'transaction_id' => $transactionId,
                                'subscription_id' => $subscriptionId,
                                'is_upgrade' => $isUpgrade,
                                'auth_check_before_redirect' => auth()->check(),
                                'auth_id_before_redirect' => auth()->id()
                            ]);

                            $successMessage = $isUpgrade
                                ? "Successfully upgraded to {$package->name}!"
                                : "Successfully subscribed to {$package->name}!";

                            if (!auth()->check()) {
                                return redirect()->route('login')->with('info', $successMessage . ' Please log in to access your dashboard.');
                            }
                            return redirect()->route('user.dashboard')->with('success', $successMessage);
                        } else {
                            Log::error('Failed to create license for Paddle order in success callback', [
                                'user_id' => $userId,
                                'package' => $package->name,
                                'transaction_id' => $transactionId,
                                'subscription_id' => $subscriptionId,
                                'auth_check_before_redirect' => auth()->check(),
                                'auth_id_before_redirect' => auth()->id()
                            ]);
                            if (!auth()->check()) {
                                return redirect()->route('login')->with('info', "Subscription to {$package->name} bought successfully! Please log in to access your dashboard.");
                            }
                            return redirect()->route('user.dashboard')->with('success', "Subscription to {$package->name} bought successfully!");
                        }
                    } else {
                        Log::error('User or package not found', [
                            'user_id' => $userId,
                            'package_id' => $pendingOrder->package_id,
                            'transaction_id' => $transactionId,
                            'auth_check_before_redirect' => auth()->check(),
                            'auth_id_before_redirect' => auth()->id()
                        ]);
                        return redirect()->route('subscription')->with('error', 'Order processing failed');
                    }
                } else {
                    // Process as regular payment (new subscription)
                    $packageName = $customData['package'] ?? null;
                    if (!$packageName) {
                        Log::error('No package name in Paddle transaction custom_data', [
                            'transaction_id' => $transactionId,
                            'custom_data' => $customData,
                            'auth_check_before_redirect' => auth()->check(),
                            'auth_id_before_redirect' => auth()->id()
                        ]);
                        return redirect()->route('subscription')->with('error', 'Invalid transaction data');
                    }

                    $result = $this->processPaddlePaymentFromWebhook($transactionData, $packageName, $userId);

                    if ($result) {
                        Log::info('Paddle payment processed successfully via success callback', [
                            'transaction_id' => $transactionId,
                            'user_id' => $userId,
                            'auth_check_before_redirect' => auth()->check(),
                            'auth_id_before_redirect' => auth()->id()
                        ]);
                        return redirect()->route('user.subscription.details')->with('success', "Subscription to {$packageName} bought successfully!");
                    } else {
                        Log::error('Failed to process Paddle payment via success callback', [
                            'transaction_id' => $transactionId,
                            'user_id' => $userId,
                            'auth_check_before_redirect' => auth()->check(),
                            'auth_id_before_redirect' => auth()->id()
                        ]);
                        return redirect()->route('subscription')->with('error', 'Payment processing failed');
                    }
                }
            } elseif ($gateway === 'fastspring') {
                $orderId = $request->input('orderId') ?? $request->query('orderId');
                $packageName = $request->input('package_name') ?? $request->query('package_name');

                $subscriptionData = $this->getSubscriptionId($orderId); // Returns the array
                // Handle errors from getSubscriptionId
                if (isset($subscriptionData['error'])) {
                    Log::error('FastSpring Error: ' . $subscriptionData['error']);
                    return redirect()->route('subscription')->with('error', $subscriptionData['error']);
                }

                // Proceed if successfull
                $subscriptionId = $subscriptionData['id'];

                if (!$orderId) {
                    Log::error('Missing FastSpring orderId', [
                        'params' => $request->all(),
                        'auth_check_before_redirect' => auth()->check(),
                        'auth_id_before_redirect' => auth()->id()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Invalid order ID');
                }

                $order = Order::where('transaction_id', $orderId)->first();
                if ($order && $order->status === 'completed') {
                    Log::info('Order already completed', ['order_id' => $orderId]);
                    Log::info('Redirect decision for completed FastSpring order', [
                        'auth_check' => auth()->check(),
                        'auth_id' => auth()->id()
                    ]);
                    if (!auth()->check()) {
                        return redirect()->route('login')->with('info', "Subscription to {$packageName} bought successfully! Please log in to access your dashboard.");
                    }
                    return redirect()->route('user.dashboard')->with('success', "Subscription to {$packageName} bought successfully!");
                }

                // Try orderId as reference first, then as id
                $response = Http::withBasicAuth(
                    config('payment.gateways.FastSpring.username'),
                    config('payment.gateways.FastSpring.password')
                )->get("https://api.fastspring.com/orders/{$orderId}");

                if (!$response->successful() || !$response->json()['orders'][0]['completed']) {
                    Log::error('FastSpring order verification failed', [
                        'order_id' => $orderId,
                        'response' => $response->body()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Order verification failed');
                }

                $orderData = $response->json()['orders'][0] ?? $response->json();

                return $this->processPayment(array_merge($orderData, [
                    'order_id' => $orderId,
                    'package' => $packageName,
                    'subscription_id' => $subscriptionId
                ]), 'fastspring');
            } elseif ($gateway === 'payproglobal') {
                Log::info('PayProGlobal success callback - RAW REQUEST INCOMING', ['request_all' => $request->all()]);

                // PayProGlobal sends data as query parameters in the success URL redirect
                // Try to get from query parameters first, then from custom JSON field
                $customData = json_decode($request->input('custom', '{}'), true);
                $userId = $request->query('user_id') ?? $request->input('user_id') ?? $customData['user_id'] ?? null;
                $packageSlug = $request->query('package') ?? $request->input('package') ?? $customData['package'] ?? null;
                $pendingOrderId = $request->query('pending_order_id') ?? $request->input('pending_order_id') ?? $customData['pending_order_id'] ?? null;
                $action = $request->query('action') ?? $request->input('action') ?? $customData['action'] ?? 'new';

                Log::info('=== PROCESSING PAYPROGLOBAL SUCCESS CALLBACK ===', [
                    'gateway' => $gateway,
                    'all_params' => $request->all(),
                    'query_params' => $request->query(),
                    'input_params' => $request->input()
                ]);

                Log::info('Extracted custom data from PayProGlobal callback', [
                    'user_id' => $userId,
                    'package_slug' => $packageSlug,
                    'pending_order_id' => $pendingOrderId,
                    'action' => $action
                ]);

                Log::info('PayProGlobal success callback - full request data', ['request_all' => $request->all()]);
                /** @var array $queryParams */
                $queryParams = $request->query()->all();
                Log::info('PayProGlobal success callback - extracted data', [
                    'user_id' => $userId,
                    'package_slug' => $packageSlug,
                    'pending_order_id' => $pendingOrderId,
                    'action' => $action,
                    'query_params' => $queryParams,
                    'input_params' => $request->input()
                ]);

                if (!$userId || !$packageSlug || !$pendingOrderId) {
                    Log::error('Missing essential PayProGlobal custom data', [
                        'user_id' => $userId,
                        'package_slug' => $packageSlug,
                        'pending_order_id' => $pendingOrderId,
                        'custom_data' => $customData,
                        'request_all' => $request->all(),
                        'query_params' => $queryParams,
                        'input_params' => $request->input(),
                        'auth_check_before_redirect' => auth()->check(),
                        'auth_id_before_redirect' => auth()->id()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Invalid payment data from PayProGlobal');
                }

                $user = User::find($userId);
                // Try to find package by name (case-insensitive) or slug
                $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageSlug)])
                    ->orWhere('name', $packageSlug)
                    ->first();
                $pendingOrder = Order::where('transaction_id', $pendingOrderId)->first();

                Log::info('PayProGlobal success callback - Entity lookup', [
                    'user_id' => $userId,
                    'user_found' => (bool) $user,
                    'package_slug' => $packageSlug,
                    'package_found' => (bool) $package,
                    'package_id' => $package ? $package->id : null,
                    'package_name' => $package ? $package->name : null,
                    'pending_order_id' => $pendingOrderId,
                    'pending_order_found' => (bool) $pendingOrder,
                    'pending_order_status' => $pendingOrder ? $pendingOrder->status : null,
                    'all_pending_orders_for_user' => $user ? Order::where('user_id', $user->id)->where('status', 'pending')->pluck('transaction_id')->toArray() : []
                ]);

                if (!$user || !$package || !$pendingOrder) {
                    Log::error('PayProGlobal: User, Package or Pending Order not found', [
                        'user_id' => $userId,
                        'package_slug' => $packageSlug,
                        'pending_order_id' => $pendingOrderId,
                        'user_exists' => (bool) $user,
                        'package_exists' => (bool) $package,
                        'order_exists' => (bool) $pendingOrder,
                        'auth_check_before_redirect' => auth()->check(),
                        'auth_id_before_redirect' => auth()->id()
                    ]);
                    return redirect()->route('subscription')->with('error', 'Payment processing error (data mismatch).');
                }

                if ($pendingOrder->status === 'completed') {
                    Log::info('PayProGlobal: Order already completed', ['order_id' => $pendingOrder->id]);
                    Log::info('Redirect decision for completed PayProGlobal order', [
                        'auth_check' => auth()->check(),
                        'auth_id' => auth()->id()
                    ]);
                    if (!auth()->check()) {
                        return redirect()->route('login')->with('info', 'Subscription is already active! Please log in to access your dashboard.');
                    }
                    return redirect()->route('user.dashboard')->with('success', 'Subscription is already active.');
                }

                DB::transaction(function () use ($user, $package, $pendingOrder, $request) {
                    Log::debug('PaymentController: Raw request for subscription ID detection', ['request_all' => $request->all()]);

                    $payProGlobalSubscriptionId = (int)($request->input('ORDER_ITEMS.0.SUBSCRIPTION_ID')
                        ?? $request->input('subscriptionId')
                        ?? $request->input('transactionId')
                        ?? $pendingOrder->transaction_id);

                    Log::debug('PaymentController: payProGlobalSubscriptionId detected', ['id' => $payProGlobalSubscriptionId]);

                    $payProGlobalOrderId = $request->input('ORDER_ID');
                    Log::debug('PaymentController: payProGlobalOrderId detected', ['id' => $payProGlobalOrderId]);

                    $finalTransactionId = $payProGlobalSubscriptionId !== 0 ? (string)$payProGlobalSubscriptionId : (string)($payProGlobalOrderId ?? $pendingOrder->transaction_id);
                    Log::debug('PaymentController: finalTransactionId for order', ['id' => $finalTransactionId]);

                    $updateResult = $pendingOrder->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'transaction_id' => $finalTransactionId,
                        'metadata' => array_merge(($pendingOrder->metadata ?? []), [
                            'subscription_id' => $payProGlobalSubscriptionId,
                            'payproglobal_order_id' => $payProGlobalOrderId,
                        ]),
                    ]);

                    Log::info('PaymentController: Order update result', [
                        'order_id' => $pendingOrder->id,
                        'update_result' => $updateResult,
                        'new_status' => $pendingOrder->fresh()->status,
                        'transaction_id' => $finalTransactionId,
                        'metadata' => $pendingOrder->metadata,
                    ]);

                    $paymentGateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
                    if (!$paymentGateway) {
                        Log::error('PayProGlobal gateway not found in database.');
                        throw new \Exception('PayProGlobal gateway not configured.');
                    }

                    $user->update([
                        'package_id' => $package->id,
                        'is_subscribed' => true,
                        'payment_gateway_id' => $paymentGateway->id,
                        'subscription_id' => $payProGlobalSubscriptionId
                    ]);

                    Log::debug('PaymentController: User updated with subscription_id', [
                        'user_id' => $user->id,
                        'user_subscription_id' => $user->subscription_id,
                    ]);

                    $this->licenseService->createAndActivateLicense(
                        $user,
                        $package,
                        $request->input('products.1.id'),
                        $paymentGateway->id
                    );

                    Log::info('PayProGlobal: User subscription and license updated successfully', [
                        'user_id' => $user->id,
                        'order_id' => $pendingOrder->id,
                        'package' => $package->name,
                    ]);
                });

                // Check if the request is from a popup (indicated by `popup=true` in success-url)
                if ($request->query('popup') === 'true') {
                    // Use production URL in production, otherwise use current request domain
                    if (app()->environment('production')) {
                        $baseUrl = 'https://app.syntopia.ai';
                    } else {
                        $baseUrl = $request->getSchemeAndHttpHost() ?: config('app.url');
                    }
                    $redirectUrl = $baseUrl . route('user.subscription.details', [], false);

                    // For popup, return a view that closes the popup and redirects parent to subscription details page
                    return view('payments.popup-close', [
                        'message' => 'Payment successful! Redirecting to subscription details...',
                        'redirectUrl' => $redirectUrl
                    ]);
                }

                Log::info('PayProGlobal: User authenticated before redirect', [
                    'user_id' => $user->id,
                    'Auth::check()' => Auth::check(),
                    'Auth::id()' => Auth::id(),
                    'auth_check_before_final_redirect' => auth()->check(),
                    'auth_id_before_final_redirect' => auth()->id()
                ]);

                // If user is not authenticated but we have the user object, log them in
                if (!auth()->check() && $user) {
                    auth()->login($user);
                    Log::info('PayProGlobal: User logged in during success callback', [
                        'user_id' => $user->id,
                        'Auth::check()' => auth()->check(),
                        'Auth::id()' => auth()->id()
                    ]);
                }

                // Determine the success message based on the action (e.g., downgrade)
                $successMessage = 'Your subscription is now active!';
                if (($customData['action'] ?? '') === 'downgrade') {
                    $scheduledActivationDate = null;
                    if ($pendingOrder && is_array($pendingOrder->metadata) && isset($pendingOrder->metadata['scheduled_activation_date'])) {
                        $scheduledActivationDate = Carbon::parse($pendingOrder->metadata['scheduled_activation_date']);
                    }
                    if ($scheduledActivationDate) {
                        $successMessage = "Downgrade to {$package->name} scheduled successfully. It will activate on " . $scheduledActivationDate->format('M d, Y') . '.';
                    } else {
                        $successMessage = "Downgrade to {$package->name} scheduled successfully. It will activate at the end of your current billing cycle.";
                    }
                }

                if (!auth()->check()) {
                    return redirect()->route('login')->with('info', $successMessage . ' Please log in to access your dashboard.');
                }
                // Redirect to subscription details page after payment success
                return redirect()->route('user.subscription.details')->with('success', $successMessage);

            } // Add other payment gateways here
            else {
                Log::error('Unhandled payment gateway in success callback', [
                    'gateway' => $gateway,
                    'params' => $request->all(),
                    'auth_check_before_redirect' => auth()->check(),
                    'auth_id_before_redirect' => auth()->id()
                ]);
                return redirect()->route('subscription')->with('error', 'Unknown payment gateway.');
            }
        } catch (\Exception $e) {
            Log::error('Payment success callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_all' => $request->all()
            ]);
            return redirect()->route('subscription')->with('error', 'There was an error processing your payment: ' . $e->getMessage());
        }
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

            Log::info('[handleAddonSuccess] called', [
                'user_id' => $user->id,
                'orderId' => $orderId,
                'addon' => $addon,
                'params' => $request->all(),
            ]);

            if (!$orderId || !$addon) {
                Log::error('Addon success missing parameters', [
                    'orderId' => $orderId,
                    'addon' => $addon,
                ]);
                return redirect()->route('subscription')->with('error', 'Invalid add-on payment parameters');
            }

            $response = Http::withBasicAuth(
                config('payment.gateways.FastSpring.username'),
                config('payment.gateways.FastSpring.password')
            )->get("https://api.fastspring.com/orders/{$orderId}");

            if (!$response->successful()) {
                Log::error('FastSpring order verification failed for add-on', [
                    'order_id' => $orderId,
                    'response' => $response->body(),
                ]);
                return redirect()->route('subscription')->with('error', 'Add-on order verification failed');
            }

            $orderJson = $response->json();
            $orderData = $orderJson['orders'][0] ?? $orderJson;

            if (!($orderData['completed'] ?? false)) {
                Log::error('FastSpring add-on order not completed', [
                    'order_id' => $orderId,
                    'orderData' => $orderData,
                ]);
                return redirect()->route('subscription')->with('error', 'Add-on payment not completed');
            }

            // Robust amount parsing from FastSpring order
            $amount = 0.0;
            if (isset($orderData['total']) && is_numeric($orderData['total'])) {
                $amount = (float)$orderData['total'];
            } elseif (isset($orderData['items'][0]['subtotal']) && is_numeric($orderData['items'][0]['subtotal'])) {
                $amount = (float)$orderData['items'][0]['subtotal'];
            } elseif (isset($orderData['totalDisplay'])) {
                $amount = (float)preg_replace('/[^\d.]/', '', (string)$orderData['totalDisplay']);
            }
            $currency = $orderData['currency']
                ?? ($orderData['items'][0]['currency'] ?? 'USD');

            // Resolve package_id from addon slug
            $addonKey = strtolower((string)$addon);
            $addonName = match ($addonKey) {
                'avatar_customization', 'avatar-customization' => 'Avatar Customization',
                'voice_customization', 'voice-customization' => 'Voice Customization',
                default => null,
            };
            $packageId = $addonName ? (\App\Models\Package::where('name', $addonName)->value('id')) : null;

            Log::info('Parsed FastSpring add-on order values', [
                'order_id' => $orderId,
                'addon' => $addon,
                'resolved_package' => $addonName,
                'package_id' => $packageId,
                'amount' => $amount,
                'currency' => $currency,
            ]);
            $paymentGatewayId = $this->getPaymentGatewayId('fastspring');

            // Create or update the order record for the add-on
            $existing = Order::where('transaction_id', $orderId)->first();
            if ($existing) {
                // Update existing if it was created earlier as pending
                $existing->update([
                    'status' => 'completed',
                    'amount' => $amount > 0 ? $amount : $existing->amount,
                    'currency' => $currency,
                    'order_type' => 'addon',
                    'payment_gateway_id' => $paymentGatewayId,
                    'package_id' => $packageId ?? $existing->package_id,
                    'metadata' => array_merge(($existing->metadata ?? []), [
                        'addon' => $addon,
                        'fastspring_order' => $orderData,
                    ]),
                ]);
                $order = $existing;
            } else {
                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => $packageId,
                    'payment_gateway_id' => $paymentGatewayId,
                    'amount' => $amount,
                    'transaction_id' => $orderId,
                    'status' => 'completed',
                    'currency' => $currency,
                    'order_type' => 'addon',
                    'metadata' => [
                        'addon' => $addon,
                        'fastspring_order' => $orderData,
                    ],
                ]);
            }

            Log::info('Add-on order stored successfully', [
                'order_db_id' => $order->id,
                'user_id' => $user->id,
                'addon' => $addon,
            ]);

            // Fulfill add-on by assigning the matching license (if such a subscription exists)
            try {
                $resolved = $this->licenseApiService->resolvePlanLicense($user->tenant_id, $addonName ?? $addonKey, true);
                if ($resolved) {
                    $licenseKey = $resolved['subscriptionCode'] ?? null;
                    if ($licenseKey) {
                        $added = $this->licenseApiService->addLicenseToTenant($user->tenant_id, $licenseKey);
                        Log::info('Addon license assignment attempted', [
                            'user_id' => $user->id,
                            'addon' => $addon,
                            'subscription_name' => $resolved['subscriptionName'] ?? null,
                            'license_key' => $licenseKey,
                            'success' => $added,
                        ]);
                    }
                } else {
                    Log::warning('Addon plan not found in API inventory', [
                        'user_id' => $user->id,
                        'addon' => $addon,
                        'note' => 'If this is a purely internal add-on, no external license is required.'
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Addon license fulfillment failed', [
                    'user_id' => $user->id,
                    'addon' => $addon,
                    'error' => $e->getMessage(),
                ]);
            }

            return redirect()->route('user.dashboard')->with('success', 'Your add-on purchase is successful.');
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

            switch ($level) {
                case 'warning':
                    Log::warning('[AddonDebug] ' . $message, $logPayload);
                    break;
                case 'error':
                    Log::error('[AddonDebug] ' . $message, $logPayload);
                    break;
                default:
                    Log::info('[AddonDebug] ' . $message, $logPayload);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('[AddonDebug] Failed to log', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    private function processPayment($paymentData, $gateway)
    {
        return DB::transaction(function () use ($paymentData, $gateway) {
            $userId = Auth::user()->id ?? null;
            $packageName = ucfirst($paymentData['package']) ?? (isset($paymentData['custom_data']) ? (ucfirst($paymentData['custom_data']['package']) ?? null) : null);
            $transactionId = $paymentData['order'] ?? ($paymentData['id'] ?? null);
            $amount = $paymentData['total'] ?? (isset($paymentData['items'][0]) ? ($paymentData['items'][0]['subtotal'] / 100 ?? 0) : 0);
            $subscriptionId = $paymentData['subscription_id'] ?? null;
            $action = $paymentData['action'] ?? (isset($paymentData['custom_data']) ? ($paymentData['custom_data']['action'] ?? 'new') : 'new');
            $currency = $paymentData['currency'] ?? 'USD';

            Log::info('=== PROCESSING PAYMENT ===', [
                'user_id' => $userId,
                'package_name' => $packageName,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'subscription_id' => $subscriptionId,
                'action' => $action,
                'gateway' => $gateway,
                'payment_data' => $paymentData
            ]);

            if (!$userId || !$packageName) {
                Log::error('Missing payment data', [
                    'user_id' => $userId,
                    'package' => $packageName,
                    'gateway' => $gateway
                ]);
                throw new \Exception('Invalid payment data');
            }

            $user = User::find($userId);
            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();

            if (!$user || !$package) {
                Log::error('Invalid payment data', [
                    'user_id' => $userId,
                    'package' => $packageName,
                    'gateway' => $gateway
                ]);
                throw new \Exception('Invalid payment data');
            }

            Log::info('Found user and package', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'package_id' => $package->id,
                'package_name' => $package->name
            ]);

            $orderData = [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                'order_type' => $action, // Set order_type based on action
                'metadata' => $paymentData
            ];

            Log::info('Creating/updating order', [
                'transaction_id' => $transactionId,
                'order_data' => $orderData
            ]);

            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                array_merge($orderData, ['status' => 'pending']) // Always set to pending first
            );

            Log::info('Order created/updated as pending', [
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'order_status' => $order->status,
                'order_amount' => $order->amount
            ]);

            // Create and activate license
            $license = null;
            Log::info('Creating and activating license for package', [
                'user_id' => $user->id,
                'package_name' => $package->name,
                'action' => $action
            ]);

            $license = $this->licenseService->createAndActivateLicense(
                $user,
                $package,
                $subscriptionId,
                $this->getPaymentGatewayId($gateway),
                $action === 'upgrade'
            );

            if (!$license) {
                Log::error('Failed to create and activate license', ['user_id' => $user->id]);
                throw new \Exception('License generation failed');
            }

            Log::info('License created and activated successfully', [
                'user_id' => $user->id,
                'license_id' => $license->id,
                'license_key' => $license->license_key
            ]);

            // Update user subscription status
            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            // Now mark order as completed
            $order->update(['status' => 'completed']);

            Log::info('=== PAYMENT PROCESSING COMPLETED ===', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'subscription_id' => $subscriptionId,
                'action' => $action,
                'final_user_status' => [
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'subscription_id' => $user->subscription_id,
                    'payment_gateway_id' => $user->payment_gateway_id
                ],
                'final_order_status' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'amount' => $order->amount
                ]
            ]);

            $successMessage = $action === 'upgrade'
                ? 'Your plan upgrade is active now.'
                : "Subscription to {$package->name} bought successfully!";
            return redirect()->route('user.subscription.details')->with('success', $successMessage);
        });
    }

    private function verifyPayProGlobalPayment($orderId, $paymentId)
    {
        try {
            $vendorAccountId = config('payment.gateways.PayProGlobal.merchant_id');
            $apiSecretKey = config('payment.gateways.PayProGlobal.webhook_secret');

            $response = Http::post('https://store.payproglobal.com/api/Orders/GetOrderDetails', [
                'vendorAccountId' => $vendorAccountId,
                'apiSecretKey' => $apiSecretKey,
                'orderId' => $orderId,
                'dateFormat' => 'a',
            ]);

            $orderData = $response->json();
            // dd($orderData);
            if (
                $response->successful() && $orderData['isSuccess'] &&
                $orderData['response']['orderStatusName'] === 'Processed' &&
                $orderData['response']['paymentStatusName'] === 'Paid'
            ) {
                Log::info('PayProGlobal payment verified', ['order_id' => $orderId]);
                return true;
            }
            Log::error('PayProGlobal verification failed', [
                'order_id' => $orderId,
                'response' => $orderData
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('PayProGlobal verification error', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function handlePaddleWebhook(Request $request)
    {
        Log::info('[handlePaddleWebhook] called', ['payload' => $request->all()]);
        try {
            $payload = $request->all();
            Log::info('Paddle webhook received', ['payload' => $payload]);

            $eventType = $payload['event_type'] ?? null;
            $eventData = $payload['data'] ?? [];

            if (!$eventType) {
                Log::error('No event type in Paddle webhook', ['payload' => $payload]);
                return response()->json(['error' => 'Invalid webhook payload'], 400);
            }

            // Verify webhook signature (optional but recommended)
            if (config('payment.gateways.Paddle.webhook_secret')) {
                $receivedSignature = $request->header('Paddle-Signature');
                $expectedSignature = hash_hmac('sha256', $request->getContent(), config('payment.gateways.Paddle.webhook_secret'));

                if (!$receivedSignature || !hash_equals($expectedSignature, $receivedSignature)) {
                    Log::error('Invalid Paddle webhook signature', [
                        'has_received_signature' => !is_null($receivedSignature),
                        'has_webhook_secret' => !empty(config('payment.gateways.Paddle.webhook_secret'))
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            // Handle different event types
            switch ($eventType) {
                case 'transaction.completed':
                case 'transaction.paid':
                    return $this->handlePaddleTransactionCompleted($eventData);

                case 'subscription.created':
                case 'subscription.updated':
                    return $this->handlePaddleSubscriptionEvent($eventData);

                case 'subscription.cancelled':
                    return $this->handlePaddleSubscriptionCancelled($eventData);

                default:
                    Log::info('Paddle webhook event ignored', ['event_type' => $eventType]);
                    return response()->json(['status' => 'ignored']);
            }
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
            $payload = $request->all();
            Log::info('FastSpring webhook received', ['payload' => $payload]);

            if (in_array($payload['type'] ?? null, ['order.completed', 'subscription.activated', 'subscription.charge.completed', 'subscription.updated'])) {
                $tags = is_string($payload['tags'] ?? '') ? json_decode($payload['tags'], true) : ($payload['tags'] ?? []);

                // Check if this is an upgrade that was scheduled for expiration
                if (isset($payload['subscription'])) {
                    $order = Order::where('metadata->subscription_id', $payload['subscription'])
                        ->where('status', 'pending_upgrade')
                        ->latest()
                        ->first();

                    if ($order) {
                        // This is an upgrade that was scheduled for expiration
                        $order->update(['status' => 'completed']);

                        // Update user's package and payment gateway
                        $user = $order->user;
                        if ($user) {
                            $user->update([
                                'package_id' => $order->package_id,
                                'is_subscribed' => true,
                                'payment_gateway_id' => $order->payment_gateway_id,
                                'subscription_id' => $payload['subscription'] ?? null
                            ]);

                            // Update license
                            $license = $user->userLicence;
                            if ($license) {
                                $license->update([
                                    'package_id' => $order->package_id,
                                    'updated_at' => now()
                                ]);
                            }
                        }

                        return response()->json(['status' => 'processed']);
                    }
                }
                $paymentData = array_merge($payload, [
                    'user_id' => $tags['user_id'] ?? null,
                    'package' => $tags['package'] ?? null,
                    'subscription_id' => $payload['subscription'] ?? null,
                    'action' => $tags['action'] ?? 'new'
                ]);
                return $this->processPayment($paymentData, 'fastspring');
            } elseif (in_array($payload['type'] ?? null, ['subscription.cancelled', 'subscription.deactivated'])) {
                // Handle subscription cancellation
                $subscriptionId = $payload['subscription'] ?? null;
                if ($subscriptionId) {
                    // Find user by subscription_id in user_licences table
                    $userLicense = \App\Models\UserLicence::where('subscription_id', $subscriptionId)->first();
                    if ($userLicense) {
                        $user = $userLicense->user;
                        DB::transaction(function () use ($user, $userLicense, $subscriptionId) {
                            Log::info('Deleting user license record from FastSpring webhook', [
                                'license_id' => $userLicense->id,
                                'user_id' => $user->id,
                                'subscription_id' => $userLicense->subscription_id
                            ]);
                            $userLicense->delete();

                            // Reset user's subscription data
                            $user->update([
                                'is_subscribed' => false,
                                'subscription_id' => null,
                                'package_id' => null,
                                'payment_gateway_id' => null,
                                'user_license_id' => null
                            ]);

                            // Update order status
                            $order = Order::where('user_id', $user->id)
                                ->latest('created_at')
                                ->first();

                            if ($order) {
                                $order->update(['status' => 'canceled']);
                                Log::info('Updated order status to canceled from FastSpring webhook', [
                                    'order_id' => $order->id,
                                    'user_id' => $user->id,
                                    'subscription_id' => $subscriptionId
                                ]);
                            }
                        });

                        Log::info('Processed subscription cancellation from FastSpring webhook', [
                            'user_id' => $user->id,
                            'subscription_id' => $subscriptionId,
                            'event_type' => $payload['type']
                        ]);
                    }
                }
                return response()->json(['status' => 'processed']);
            }
            Log::info('FastSpring webhook ignored', ['type' => $payload['type']]);
            return response()->json(['status' => 'ignored']);
        } catch (\Exception $e) {
            Log::error('FastSpring webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            // Check if this is a license API failure
            if ($e->getMessage() === 'license_api_failed') {
                Log::error('License API failed during FastSpring webhook processing', [
                    'payload' => $request->all()
                ]);
                // For webhooks, we return a 200 status to prevent retries, but log the error
                return response()->json(['status' => 'failed_license_api'], 200);
            }

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        Log::info('PayProGlobal Webhook received', ['ip' => $request->ip(), 'full_request' => $request->all(), 'content' => $request->getContent()]);

        $contentType = $request->header('Content-Type');
        $payload = [];

        if (str_contains($contentType, 'application/json')) {
            $payload = $request->json()->all();
        } else {
            parse_str($request->getContent(), $payload);
        }

        try {
            if (empty($payload)) {
                Log::error('Empty PayProGlobal webhook payload');
                return response()->json(['success' => false, 'error' => 'Empty payload'], 400);
            }

            // Extract key fields from the webhook
            $orderId = $payload['ORDER_ID'] ?? null;
            $ipnType = $payload['IPN_TYPE_NAME'] ?? null;
            $orderStatus = $payload['ORDER_STATUS'] ?? null;
            $customerEmail = $payload['CUSTOMER_EMAIL'] ?? null;
            $productId = $payload['PRODUCT_ID'] ?? null;
            $orderTotal = $payload['ORDER_TOTAL_AMOUNT'] ?? null;
            $currency = $payload['ORDER_CURRENCY_CODE'] ?? null;
            $subscriptionId = $payload['SUBSCRIPTION_ID'] ?? null;
            $checkoutQueryString = $payload['CHECKOUT_QUERY_STRING'] ?? null;

            $customUserId = null;
            if ($checkoutQueryString) {
                parse_str($checkoutQueryString, $checkoutParams);
                if (isset($checkoutParams['custom'])) {
                    $decodedCustom = json_decode($checkoutParams['custom'], true);
                    $customUserId = $decodedCustom['user_id'] ?? null;
                }
            }

            Log::info('PayProGlobal webhook fields extracted (critical for processing)', [
                'order_id' => $orderId,
                'ipn_type' => $ipnType,
                'customer_email' => $customerEmail,
                'product_id' => $productId,
                'subscription_id' => $subscriptionId
            ]);

            if ($ipnType === 'OrderCharged' && $orderId) {
                Log::info('PayProGlobal OrderCharged event detected and processing initiated', [
                    'order_id' => $orderId,
                    'subscription_id' => $subscriptionId,
                    'customer_email' => $customerEmail
                ]);

                if (is_null($subscriptionId)) {
                    Log::error('PayProGlobal webhook: SUBSCRIPTION_ID is missing for OrderCharged event, cannot process subscription.', [
                        'order_id' => $orderId,
                        'customer_email' => $customerEmail,
                        'payload' => $payload
                    ]);
                    return response()->json(['success' => false, 'error' => 'Missing subscription ID'], 400);
                }

                // Find user by email (fallback) or by custom user ID
                $user = null;
                if ($customUserId) {
                    $user = User::find($customUserId);
                }

                if (!$user) {
                    // Fallback to customer email if customUserId did not yield a user
                    $user = User::where('email', $customerEmail)->first();
                }

                if (!$user) {
                    Log::error('PayProGlobal webhook: User not found by email or custom user ID, cannot process order', [
                        'customer_email' => $customerEmail,
                        'custom_user_id' => $customUserId,
                        'order_id' => $orderId,
                        'subscription_id' => $subscriptionId,
                        'payload' => $payload
                    ]);
                    return response()->json(['success' => false, 'error' => 'User not found'], 404);
                }

                Log::info('PayProGlobal webhook: User found for order processing', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'found_by' => $customUserId ? 'custom_user_id' : 'customer_email',
                    'order_id' => $orderId
                ]);

                // Find package by product ID
                $package = Package::where('payproglobal_product_id', $productId)->first();
                if (!$package) {
                    Log::error('PayProGlobal webhook: Package not found by product ID, cannot process order', [
                        'product_id' => $productId,
                        'order_id' => $orderId,
                        'subscription_id' => $subscriptionId
                    ]);
                    return response()->json(['success' => false, 'error' => 'Package not found'], 404);
                }

                Log::info('PayProGlobal webhook: Package found for order processing', [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'product_id' => $productId
                ]);

                // Find the pending order for this user and package
                $pendingOrder = Order::where('user_id', $user->id)
                    ->where('package_id', $package->id)
                    ->where('status', 'pending')
                    ->where('payment_gateway_id', $this->getPaymentGatewayId('payproglobal'))
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$pendingOrder) {
                    Log::error('PayProGlobal webhook: Pending order not found, potential new order or missed initial setup', [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'payproglobal_order_id' => $orderId,
                        'subscription_id' => $subscriptionId
                    ]);
                    // For production, consider if you need to create an order here if it's genuinely a new one.
                    return response()->json(['success' => false, 'error' => 'Pending order not found'], 404);
                }

                Log::info('PayProGlobal webhook: Pending order found for update', [
                    'pending_order_id' => $pendingOrder->id,
                    'pending_transaction_id' => $pendingOrder->transaction_id,
                    'payproglobal_order_id' => $orderId,
                    'subscription_id' => $subscriptionId
                ]);

                // Update the pending order to completed
                DB::transaction(function () use ($user, $package, $pendingOrder, $orderId, $orderTotal, $currency, $subscriptionId) {
                    $paymentGateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
                    if (!$paymentGateway) {
                        Log::error('PayProGlobal gateway not found in database, critical configuration error.');
                        throw new \Exception('PayProGlobal gateway not configured.');
                    }

                    // Refresh order before update
                    $pendingOrder->refresh();

                    Log::info('PayProGlobal webhook: Updating order status to completed', [
                        'order_id' => $pendingOrder->id,
                        'current_status' => $pendingOrder->status,
                        'new_status' => 'completed',
                        'payproglobal_order_id' => $orderId,
                        'subscription_id' => $subscriptionId,
                        'retrieved_pending_order_transaction_id' => $pendingOrder->transaction_id
                    ]);

                    // Update order status
                    $updateResult = $pendingOrder->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'transaction_id' => (string)$orderId, // Use PayProGlobal's order ID
                        'amount' => $orderTotal ?? $pendingOrder->amount,
                        'currency' => $currency ?? $pendingOrder->currency,
                        'metadata' => array_merge(($pendingOrder->metadata ?? []), [
                            'payproglobal_order_id' => $orderId,
                            'webhook_processed_at' => now()->toDateTimeString(),
                            'payproglobal_subscription_id' => $subscriptionId // Store subscription ID in metadata
                        ]),
                    ]);

                    // Refresh to verify update
                    $pendingOrder->refresh();

                    Log::info('PayProGlobal webhook: Order update result verified', [
                        'order_id' => $pendingOrder->id,
                        'update_result' => $updateResult,
                        'final_status' => $pendingOrder->status,
                        'transaction_id' => $pendingOrder->transaction_id,
                        'subscription_id' => $subscriptionId
                    ]);

                    // Update user subscription
                    $user->update([
                        'package_id' => $package->id,
                        'is_subscribed' => true,
                        'payment_gateway_id' => $paymentGateway->id,
                        'subscription_id' => $subscriptionId // Use the extracted subscription ID
                    ]);

                    // Create and activate license
                    $this->licenseService->createAndActivateLicense(
                        $user,
                        $package,
                        $subscriptionId, // Pass the extracted subscription ID
                        $paymentGateway->id
                    );

                    Log::info('PayProGlobal webhook: Order completed and license created successfully', [
                        'order_id' => $pendingOrder->id,
                        'user_id' => $user->id,
                        'package' => $package->name,
                        'order_status' => $pendingOrder->status,
                        'subscription_id' => $subscriptionId
                    ]);
                });

                $result = true;

                if ($result) {
                    Log::info('PayProGlobal webhook: Payment processed successfully, license assigned', [
                        'order_id' => $orderId,
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId
                    ]);
                    return response()->json(['success' => true, 'message' => 'Payment processed', 'order_id' => $orderId], 200);
                } else {
                    Log::error('PayProGlobal webhook: Payment processing failed unexpectedly', [
                        'order_id' => $orderId,
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId
                    ]);
                    return response()->json(['success' => false, 'error' => 'Payment processing failed'], 500);
                }
            }

            Log::info('PayProGlobal webhook: Event ignored - not an OrderCharged event or missing order ID', [
                'ipn_type' => $ipnType,
                'order_id' => $orderId,
                'subscription_id' => $subscriptionId
            ]);

            return response()->json(['success' => false, 'message' => 'Event ignored'], 200);
        } catch (\Exception $e) {
            Log::error('PayProGlobal webhook processing error encountered', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload // Only log payload on error for debugging
            ]);

            // Check if this is a license API failure
            if ($e->getMessage() === 'license_api_failed') {
                Log::error('License API failed during PayProGlobal webhook processing (retries prevented)', [
                    'payload' => $payload,
                    'order_id' => $orderId,
                    'subscription_id' => $subscriptionId
                ]);
                // For webhooks, we return a 200 status to prevent retries, but log the error
                return response()->json(['success' => false, 'status' => 'failed_license_api'], 200);
            }
            return response()->json(['success' => false, 'error' => 'Internal Server Error'], 500);
        }
    }

    public function getOrdersList(Request $request)
    {
        Log::info('[getOrdersList] called', ['user_id' => Auth::id()]);
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
            ->where('status', '!=', 'Processing') // Exclude Processing status
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function cancelSubscription(Request $request)
    {
        Log::info('[cancelSubscription] called', ['user_id' => Auth::id()]);
        Log::info('Subscription cancellation started', ['user_id' => Auth::id()]);

        // Debug request details
        Log::info('Request details', [
            'wantsJson' => $request->wantsJson(),
            'accept' => $request->header('Accept'),
            'contentType' => $request->header('Content-Type'),
            'method' => $request->method(),
            'url' => $request->url()
        ]);

        try {
            $user = Auth::user();
            if (!$user->is_subscribed) {
                Log::error('User has no active subscription to cancel', ['user_id' => $user->id]);
                $errorMessage = 'No active subscription found to cancel. Please ensure you have an active subscription before attempting to cancel.';

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => $errorMessage
                    ], 400);
                }

                return redirect()->route('user.subscription.details')->with('error', $errorMessage);
            }

            // Check if user has subscription_id in user_licences table
            $userLicense = $user->userLicence;

            $gateway = $user->paymentGateway ? $user->paymentGateway->name : null;
            $gateway = $gateway ? strtolower($gateway) : null;

            // For FastSpring, require subscription_id - return error if missing
            if ($gateway === 'fastspring') {
                if (!$userLicense || !$userLicense->subscription_id) {
                    Log::error('FastSpring cancellation requires subscription_id', [
                        'user_id' => $user->id,
                        'has_license' => $userLicense !== null,
                        'subscription_id' => $userLicense?->subscription_id
                    ]);

                    $errorMessage = 'No subscription ID found. Please contact support.';

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => $errorMessage
                        ], 400);
                    }

                    return redirect()->route('user.subscription.details')->with('error', $errorMessage);
                }
            }

            if (!$userLicense || !$userLicense->subscription_id) {
                Log::info('User has subscription but no subscription_id in license, marking as cancelled in database', [
                    'user_id' => $user->id,
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'payment_gateway_id' => $user->payment_gateway_id
                ]);

                // Mark subscription as cancelled in database only
                DB::transaction(function () use ($user) {
                    // Delete the user's license record
                    $userLicense = $user->userLicence;
                    if ($userLicense) {
                        Log::info('Deleting user license record', [
                            'license_id' => $userLicense->id,
                            'user_id' => $user->id,
                            'subscription_id' => $userLicense->subscription_id
                        ]);
                        $userLicense->delete();
                    }

                    // Reset user's subscription data
                    $user->update([
                        'is_subscribed' => 0,
                        'package_id' => null,
                        'payment_gateway_id' => null,
                        'subscription_id' => null,
                        'user_license_id' => null
                    ]);

                    // Update order status
                    $order = Order::where('user_id', $user->id)
                        ->latest('created_at')
                        ->first();

                    if ($order) {
                        $order->update(['status' => 'canceled']);
                        Log::info('Updated order status to canceled', [
                            'order_id' => $order->id,
                            'user_id' => $user->id
                        ]);
                    }
                });

                Log::info('Subscription marked as cancelled in database (no external subscription_id)', [
                    'user_id' => $user->id
                ]);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Subscription cancelled successfully'
                    ]);
                }

                return redirect()->route('user.subscription.details')->with('success', 'Subscription cancelled successfully');
            }

            $subscriptionId = $userLicense->subscription_id;

            if (!$gateway) {
                Log::error('No payment gateway associated with user', ['user_id' => $user->id]);
                $errorMessage = 'No payment gateway found';

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => $errorMessage
                    ], 400);
                }

                return redirect()->route('user.subscription.details')->with('error', $errorMessage);
            }

            if ($gateway === 'fastspring') {
                $fastSpringClient = new \App\Services\FastSpringClient(
                    config('payment.gateways.FastSpring.username'),
                    config('payment.gateways.FastSpring.password')
                );

                $response = $fastSpringClient->cancelSubscription($subscriptionId, 1); // billingPeriod = 1 (end of billing period)

                $responseData = $response->json();

                // Check if the response indicates success, even if HTTP status is not 200
                $isSuccess = false;
                $errorMessage = null;

                if (isset($responseData['subscriptions']) && is_array($responseData['subscriptions'])) {
                    foreach ($responseData['subscriptions'] as $subscription) {
                        if (isset($subscription['result']) && $subscription['result'] === 'success') {
                            $isSuccess = true;
                            break;
                        }
                        if (isset($subscription['error'])) {
                            $errorMessage = $subscription['error']['subscription'] ?? 'Unknown error';
                        }
                    }
                }

                // If not successful, log and return error
                if (!$isSuccess) {
                    Log::error('FastSpring subscription cancellation failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'response' => $response->body(),
                        'status' => $response->status(),
                        'error_message' => $errorMessage
                    ]);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Failed to cancel subscription: ' . ($errorMessage ?? 'Unknown error')
                        ], 500);
                    }
                    return redirect()->route('user.subscription.details')->with('error', 'Failed to cancel subscription: ' . ($errorMessage ?? 'Unknown error'));
                }

                Log::info('FastSpring cancellation response', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'response_data' => $responseData
                ]);

                // For FastSpring, we don't immediately update the user's subscription status
                // as the cancellation happens at the end of the billing period
                // The webhook will handle the actual status change when the cancellation takes effect

                // Update order status to indicate cancellation is scheduled
                $order = Order::where('user_id', $user->id)
                    ->latest('created_at')
                    ->first();
                if ($order) {
                    $order->update(['status' => 'cancellation_scheduled']);
                }

                Log::info('FastSpring subscription cancellation scheduled', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'cancellation_type' => 'end_of_billing_period'
                ]);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.'
                    ]);
                }
                return redirect()->route('user.subscription.details')->with('success', 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.');
            } elseif ($gateway === 'paddle') {
                // Use PaddleClient for cancellation with end-of-billing-period by default
                $paddleClient = new \App\Services\PaddleClient(config('payment.gateways.Paddle.api_key'));

                Log::info('Canceling Paddle subscription', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'cancellation_type' => 'end_of_billing_period'
                ]);

                // Pass 1 for end of billing period (1 = next_billing_period, 0 = immediately)
                $response = $paddleClient->cancelSubscription($subscriptionId, 1);

                if (!$response) {
                    Log::error('Paddle subscription cancellation failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'error' => 'No response from Paddle API'
                    ]);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Failed to cancel subscription'
                        ], 500);
                    }
                    return redirect()->route('user.subscription.details')->with('error', 'Failed to cancel subscription');
                }

                Log::info('Paddle cancellation response', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'response_data' => $response,
                    'cancellation_type' => 'end_of_billing_period'
                ]);

                // Update order status to indicate cancellation is scheduled
                $order = Order::where('user_id', $user->id)
                    ->latest('created_at')
                    ->first();
                if ($order) {
                    $order->update(['status' => 'cancellation_scheduled']);
                }

                Log::info('Paddle subscription cancellation scheduled', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'cancellation_type' => 'end_of_billing_period'
                ]);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.'
                    ]);
                }
                return redirect()->route('user.subscription.details')->with('success', 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.');
            } else { // This block will now handle PayProGlobal and any other unhandled gateways
                // Delegate to SubscriptionService for cancellation, which handles PayProGlobal and other unhandled gateways
                Log::info('Delegating cancellation to SubscriptionService', [
                    'user_id' => $user->id,
                    'gateway' => $gateway
                ]);

                $cancellationReasonId = $request->input('cancellation_reason_id');
                $reasonText = $request->input('reason_text');

                try {
                    $this->subscriptionService->cancelSubscription(
                        $user,
                        $cancellationReasonId,
                        $reasonText
                    );

                    Log::info('Subscription cancellation scheduled successfully by SubscriptionService', [
                        'user_id' => $user->id,
                        'gateway' => $gateway,
                    ]);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Subscription cancellation scheduled successfully.'
                        ]);
                    }
                    return redirect()->route('user.subscription.details')->with('success', 'Subscription cancellation scheduled successfully.');
                } catch (\Exception $e) {
                    Log::error('Subscription cancellation failed via SubscriptionService', [
                        'user_id' => $user->id,
                        'gateway' => $gateway,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Failed to cancel subscription.'
                        ], 500);
                    }
                    return redirect()->route('user.subscription.details')->with('error', 'Failed to cancel subscription.');
                }
            }
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cancellation failed'
                ], 500);
            }

            return redirect()->route('user.subscription.details')->with('error', 'Cancellation failed');
        }
    }

    private function handlePaddleTransactionCompleted($eventData)
    {
        try {
            $transactionId = $eventData['id'] ?? null;
            $customData = $eventData['custom_data'] ?? [];

            if (!$transactionId) {
                Log::error('No transaction ID in Paddle webhook');
                return response()->json(['error' => 'Missing transaction ID'], 400);
            }

            // Check if this transaction is already processed
            $order = Order::where('transaction_id', $transactionId)->first();
            if ($order && $order->status === 'completed') {
                Log::info('Paddle transaction already processed', ['transaction_id' => $transactionId]);
                return response()->json(['status' => 'already_processed']);
            }

            $userId = $customData['user_id'] ?? null;
            $packageName = $customData['package'] ?? null;

            if (!$userId || !$packageName) {
                Log::error('Missing user ID or package in Paddle webhook custom data', [
                    'transaction_id' => $transactionId,
                    'custom_data' => $customData
                ]);
                return response()->json(['error' => 'Missing required data'], 400);
            }

            // Check if this is a pending order
            $pendingOrder = Order::where('transaction_id', $transactionId)
                ->where('status', 'pending')
                ->first();

            if ($pendingOrder) {
                Log::info('Processing Paddle pending order', [
                    'transaction_id' => $transactionId,
                    'order_id' => $pendingOrder->id,
                    'user_id' => $userId,
                    'package' => $packageName,
                    'order_type' => $pendingOrder->order_type
                ]);

                // Check if this is an upgrade order
                $isUpgrade = ($customData['upgrade_type'] ?? '') === 'subscription_upgrade' || $pendingOrder->order_type === 'upgrade';

                // Update the order status
                $pendingOrder->update(['status' => 'completed']);

                // Get the user and package
                $user = User::find($userId);
                $package = Package::where('name', $packageName)->first();

                if ($user && $package) {
                    // Get subscription_id from transaction data if available
                    $subscriptionId = $eventData['subscription_id'] ?? ($pendingOrder->metadata['subscription_id'] ?? null) ?? null;

                    // For Paddle, if no subscription_id is available, generate one based on transaction
                    if (!$subscriptionId) {
                        $subscriptionId = 'PADDLE-' . $transactionId;
                        Log::info('Generated subscription_id for Paddle order in webhook', [
                            'user_id' => $userId,
                            'transaction_id' => $transactionId,
                            'generated_subscription_id' => $subscriptionId
                        ]);
                    }

                    // Update user's package, subscription status, and payment gateway
                    $user->update([
                        'package_id' => $package->id,
                        'is_subscribed' => true,
                        'payment_gateway_id' => $pendingOrder->payment_gateway_id,
                        'subscription_id' => $subscriptionId
                    ]);

                    // Create or update license
                    $license = $this->licenseService->createAndActivateLicense(
                        $user,
                        $package,
                        $subscriptionId,
                        $pendingOrder->payment_gateway_id
                    );

                    if ($license) {
                        Log::info('License created successfully for Paddle order', [
                            'user_id' => $userId,
                            'package' => $packageName,
                            'license_id' => $license->id,
                            'transaction_id' => $transactionId,
                            'subscription_id' => $subscriptionId,
                            'is_upgrade' => $isUpgrade
                        ]);
                    } else {
                        Log::error('Failed to create license for Paddle order', [
                            'user_id' => $userId,
                            'package' => $packageName,
                            'transaction_id' => $transactionId,
                            'subscription_id' => $subscriptionId
                        ]);
                    }
                }

                return response()->json(['status' => 'processed']);
            }

            // Process regular payment (new subscription)
            $result = $this->processPaddlePaymentFromWebhook($eventData, $packageName, $userId);

            if ($result) {
                Log::info('Paddle webhook transaction processed successfully', [
                    'transaction_id' => $transactionId,
                    'user_id' => $userId,
                    'package' => $packageName
                ]);
                return response()->json(['status' => 'processed']);
            } else {
                Log::error('Failed to process Paddle webhook transaction', [
                    'transaction_id' => $transactionId
                ]);

                return response()->json(['error' => 'Processing failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error handling Paddle transaction completed webhook', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);

            // Check if this is a license API failure
            if ($e->getMessage() === 'license_api_failed') {
                Log::error('License API failed during webhook processing', [
                    'transaction_id' => $eventData['id'] ?? null,
                    'event_data' => $eventData
                ]);
                // For webhooks, we return a 200 status to prevent retries, but log the error
                return response()->json(['status' => 'failed_license_api'], 200);
            }

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function handlePaddleSubscriptionEvent($eventData)
    {
        try {
            $subscriptionId = $eventData['id'] ?? null;
            $customData = $eventData['custom_data'] ?? [];
            $userId = $customData['user_id'] ?? null;

            if (!$subscriptionId || !$userId) {
                Log::warning('Incomplete subscription data in Paddle webhook', [
                    'subscription_id' => $subscriptionId,
                    'user_id' => $userId
                ]);
                return response()->json(['status' => 'incomplete_data']);
            }

            $user = User::find($userId);
            if ($user) {
                $user->update(['subscription_id' => $subscriptionId]);
                Log::info('Updated user subscription ID from Paddle webhook', [
                    'user_id' => $userId,
                    'subscription_id' => $subscriptionId
                ]);

                // Check if there are any pending orders for this subscription
                $pendingOrder = Order::where('metadata->subscription_id', $subscriptionId)
                    ->whereIn('status', ['pending', 'pending_upgrade'])
                    ->first();

                if ($pendingOrder) {
                    Log::info('Found pending order for subscription update', [
                        'subscription_id' => $subscriptionId,
                        'order_id' => $pendingOrder->id,
                        'user_id' => $userId,
                        'order_status' => $pendingOrder->status,
                        'order_type' => $pendingOrder->order_type
                    ]);

                    // Update the order status
                    $pendingOrder->update(['status' => 'completed']);

                    // Get the package
                    $package = Package::find($pendingOrder->package_id);

                    if ($package) {
                        // Update user's package and subscription status
                        $user->update([
                            'package_id' => $package->id,
                            'is_subscribed' => true
                        ]);

                        // Create or update license for the upgrade
                        $license = $this->licenseService->createAndActivateLicense(
                            $user,
                            $package,
                            $subscriptionId,
                            $pendingOrder->payment_gateway_id
                        );

                        if ($license) {
                            Log::info('License created successfully for Paddle subscription update', [
                                'user_id' => $userId,
                                'package' => $package->name,
                                'license_id' => $license->id,
                                'subscription_id' => $subscriptionId,
                                'upgrade_type' => $pendingOrder->order_type
                            ]);
                        } else {
                            Log::error('Failed to create license for Paddle subscription update', [
                                'user_id' => $userId,
                                'package' => $package->name,
                                'subscription_id' => $subscriptionId
                            ]);
                        }
                    }
                }

                // Remove scheduled upgrade handling: upgrades take effect immediately
            }

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('Error handling Paddle subscription webhook', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function handlePaddleSubscriptionCancelled($eventData)
    {
        try {
            $subscriptionId = $eventData['id'] ?? null;

            if (!$subscriptionId) {
                Log::warning('No subscription ID in cancellation webhook');
                return response()->json(['status' => 'no_subscription_id']);
            }

            Log::info('Processing Paddle subscription cancellation webhook', [
                'subscription_id' => $subscriptionId,
                'event_data' => $eventData
            ]);

            // Find user by subscription_id in user_licences table first
            $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
            $user = null;

            if ($userLicense) {
                $user = $userLicense->user;
            } else {
                // Fallback: try to find user directly by subscription_id
                $user = User::where('subscription_id', $subscriptionId)->first();
            }

            if (!$user) {
                Log::warning('No user found for cancelled subscription', [
                    'subscription_id' => $subscriptionId
                ]);
                return response()->json(['status' => 'user_not_found']);
            }

            Log::info('Found user for cancellation processing', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'current_is_subscribed' => $user->is_subscribed,
                'current_package_id' => $user->package_id
            ]);

            DB::transaction(function () use ($user, $subscriptionId, $userLicense) {
                // Delete the user's license record
                if ($userLicense) {
                    Log::info('Deleting user license record from Paddle webhook', [
                        'license_id' => $userLicense->id,
                        'user_id' => $user->id,
                        'subscription_id' => $userLicense->subscription_id
                    ]);
                    $userLicense->forceDelete();
                }

                // Reset user's subscription data
                $updateData = [
                    'is_subscribed' => false,
                    'package_id' => null,
                    'payment_gateway_id' => null,
                    'user_license_id' => null
                ];

                // Only update subscription_id if the column exists
                if (Schema::hasColumn('users', 'subscription_id')) {
                    $updateData['subscription_id'] = null;
                }

                $user->update($updateData);

                // Update all orders with this subscription_id to canceled status
                $orders = Order::where('user_id', $user->id)
                    ->where('status', 'cancellation_scheduled')
                    ->get();

                foreach ($orders as $order) {
                    $order->update(['status' => 'canceled']);
                    Log::info('Updated order status to canceled from Paddle webhook', [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'previous_status' => 'cancellation_scheduled'
                    ]);
                }

                // Also update any orders with the specific subscription_id
                $specificOrders = Order::where('user_id', $user->id)
                    ->where('metadata->subscription_id', $subscriptionId)
                    ->where('status', '!=', 'canceled')
                    ->get();

                foreach ($specificOrders as $order) {
                    $order->update(['status' => 'canceled']);
                    Log::info('Updated specific order status to canceled from Paddle webhook', [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId
                    ]);
                }
            });

            Log::info('Successfully processed subscription cancellation from Paddle webhook', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'cancellation_type' => 'end_of_billing_period'
            ]);

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('Error handling Paddle cancellation webhook', [
                'error' => $e->getMessage(),
                'event_data' => $eventData,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function processPaddlePaymentFromWebhook($transactionData, $packageName, $userId)
    {
        return DB::transaction(function () use ($transactionData, $packageName, $userId) {
            $transactionId = $transactionData['id'];

            Log::info('=== PROCESSING PADDLE PAYMENT FROM WEBHOOK ===', [
                'transaction_id' => $transactionId,
                'package_name' => $packageName,
                'user_id' => $userId,
                'transaction_data' => $transactionData
            ]);

            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found in processPaddlePaymentFromWebhook', ['user_id' => $userId]);
                throw new \Exception("User not found: {$userId}");
            }

            Log::info('User found for webhook processing', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'current_is_subscribed' => $user->is_subscribed,
                'current_package_id' => $user->package_id
            ]);

            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
            if (!$package) {
                Log::error('Package not found in processPaddlePaymentFromWebhook', ['package_name' => $packageName]);
                throw new \Exception("Package not found: {$packageName}");
            }

            Log::info('Package found for webhook processing', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_price' => $package->price
            ]);

            $amount = $transactionData['details']['totals']['total'] / 100; // Convert from cents
            $currency = $transactionData['currency_code'];

            Log::info('Calculated transaction details', [
                'transaction_id' => $transactionId,
                'amount_cents' => $transactionData['details']['totals']['total'],
                'amount_dollars' => $amount,
                'currency' => $currency
            ]);

            $orderData = [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'status' => 'completed',
                'metadata' => $transactionData
            ];

            Log::info('Creating/updating order from webhook', [
                'transaction_id' => $transactionId,
                'order_data' => $orderData
            ]);

            // Create or update the order
            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                $orderData
            );

            Log::info('Order created/updated from webhook', [
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'order_status' => $order->status,
                'order_amount' => $order->amount,
                'order_user_id' => $order->user_id,
                'order_package_id' => $order->package_id
            ]);

            // Create and activate license from webhook
            $license = null;

            // Get subscription_id from transaction data if available
            $subscriptionId = $transactionData['subscription_id'] ?? null;

            // For Paddle, if no subscription_id is available, generate one based on transaction
            if (!$subscriptionId) {
                $subscriptionId = 'PADDLE-' . $transactionId;
                Log::info('Generated subscription_id for Paddle webhook processing', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'generated_subscription_id' => $subscriptionId
                ]);
            }

            Log::info('Creating and activating license from webhook for package', [
                'user_id' => $user->id,
                'package_name' => $package->name,
                'subscription_id' => $subscriptionId
            ]);

            $license = $this->licenseService->createAndActivateLicense(
                $user,
                $package,
                $subscriptionId,
                $this->getPaymentGatewayId('paddle')
            );

            if ($license) {
                // Update user's user_license_id to link to the created license
                $user->update(['user_license_id' => $license->id]);

                Log::info('License created and user license_id updated', [
                    'user_id' => $user->id,
                    'license_id' => $license->id,
                    'user_license_id' => $license->id
                ]);
            } else {
                Log::error('Failed to create and activate license from webhook', [
                    'user_id' => $user->id,
                    'package_name' => $package->name
                ]);
                throw new \Exception('License generation failed');
            }

            // Update user subscription status
            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'package_id' => $package->id,
                'is_subscribed' => true,
                'subscription_id' => $subscriptionId
            ]);

            Log::info('User updated successfully from webhook', [
                'user_id' => $user->id,
                'is_subscribed' => $user->is_subscribed,
                'package_id' => $user->package_id,
                'subscription_id' => $user->subscription_id,
                'payment_gateway_id' => $user->payment_gateway_id
            ]);

            Log::info('=== PADDLE WEBHOOK PROCESSING COMPLETED ===', [
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'final_user_status' => [
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'subscription_id' => $user->subscription_id,
                    'payment_gateway_id' => $user->payment_gateway_id
                ],
                'final_order_status' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'amount' => $order->amount
                ]
            ]);

            return true;
        });
    }

    public function upgradeToPackage(Request $request, string $package)
    {
        Log::info('[upgradeToPackage] called', ['package' => $package, 'user_id' => Auth::id()]);
        Log::info('Package upgrade requested', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            if (!$user->is_subscribed) {
                return response()->json([
                    'error' => 'Subscription Required',
                    'message' => 'You need an active subscription to upgrade your package. Please purchase a subscription first.',
                    'action' => 'purchase_subscription'
                ], 400);
            }

            $currentLicense = $user->userLicence;
            if (!$currentLicense || !$currentLicense->subscription_id) {
                return response()->json([
                    'error' => 'License Configuration Issue',
                    'message' => 'Your software license is not properly configured for upgrades. This usually happens when your license details are missing or incomplete. Please contact our support team to verify and fix your license configuration.',
                    'action' => 'contact_support',
                    'details' => 'License record or subscription ID is missing'
                ], 400);
            }

            $subscriptionId = $currentLicense->subscription_id;

            Log::info('Starting package upgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'package_price' => $packageData->price,
                'current_subscription_id' => $subscriptionId,
                'user_payment_gateway_id' => $user->payment_gateway_id,
                'user_paddle_customer_id' => $user->paddle_customer_id,
                'user_is_subscribed' => $user->is_subscribed
            ]);

            $gateway = null;

            if ($user->payment_gateway_id) {
                $gateway = $user->paymentGateway;
            }

            if (!$gateway && $subscriptionId) {
                if (str_starts_with($subscriptionId, 'PADDLE-') || str_starts_with($subscriptionId, 'sub_')) {
                    $gateway = PaymentGateways::where('name', 'Paddle')->first();
                } elseif (str_starts_with($subscriptionId, 'FS-') || str_starts_with($subscriptionId, 'fastspring_')) {
                    $gateway = PaymentGateways::where('name', 'FastSpring')->first();
                } elseif (str_starts_with($subscriptionId, 'PPG-') || str_starts_with($subscriptionId, 'payproglobal_')) {
                    $gateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
                }
            }

            if (!$gateway && $user->paddle_customer_id) {
                $gateway = PaymentGateways::where('name', 'Paddle')->first();
            }

            if (!$gateway) {
                Log::error('Could not determine payment gateway for upgrade', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'user_payment_gateway_id' => $user->payment_gateway_id,
                    'user_paddle_customer_id' => $user->paddle_customer_id
                ]);
                return response()->json([
                    'error' => 'Payment Method Not Found',
                    'message' => 'We couldn\'t determine your payment method. Please contact support to resolve this issue.',
                    'action' => 'contact_support'
                ], 400);
            }

            Log::info('Payment gateway determined for upgrade', [
                'user_id' => $user->id,
                'gateway_name' => $gateway->name,
                'gateway_id' => $gateway->id
            ]);

            if ($gateway->name === 'Paddle') {
                if (!$user->paddle_customer_id) {
                    $paddleCustomerId = $this->ensurePaddleCustomerId($user);
                    if (!$paddleCustomerId) {
                        return response()->json([
                            'error' => 'Paddle Customer ID Missing',
                            'message' => 'Your Paddle customer information is missing. Please contact support to resolve this issue.',
                            'action' => 'contact_support'
                        ], 400);
                    }
                }
                return $this->handlePaddleUpgrade($user, $packageData, $subscriptionId);
            } elseif ($gateway->name === 'FastSpring') {
                return $this->handleFastSpringUpgrade(
                    $user,
                    $packageData,
                    $subscriptionId
                );
            } elseif ($gateway->name === 'Pay Pro Global') {
                return $this->handlePayProGlobalUpgrade($user, $packageData, $subscriptionId);
            } else {
                return response()->json([
                    'error' => 'Payment Method Not Supported',
                    'message' => 'Your current payment method doesn\'t support package upgrades. Please contact support for assistance.',
                    'action' => 'contact_support'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Package upgrade error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'error' => 'Upgrade Failed',
                'message' => 'We encountered an error while processing your upgrade. Please try again or contact support if the problem persists.',
                'action' => 'retry_or_contact_support'
            ], 500);
        }
    }

    private function handlePaddleUpgrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting Paddle upgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // Immediate upgrade flow
            $checkoutUrl = $this->createPaddleUpgradeOrder($user, $packageData, $subscriptionId);

            if (!$checkoutUrl) {
                Log::error('Failed to create Paddle upgrade order', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return response()->json(['error' => 'Failed to create upgrade order'], 500);
            }

            Log::info('Paddle upgrade order created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'message' => 'Upgrade order created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle upgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);
            return response()->json([
                'error' => 'Upgrade Failed',
                'message' => 'We encountered an error while processing your upgrade. Please try again or contact support if the problem persists.',
                'action' => 'retry_or_contact_support'
            ], 500);
        }
    }

    // Removed upgradeFastSpringSubscriptionAtExpiration: upgrades take effect immediately

    private function upgradePaddleSubscriptionAtExpiration($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting Paddle upgrade at expiration process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for upgrade at expiration');
                return false;
            }

            // Get the transaction_id from the user's current license or order
            $currentLicense = $user->userLicence;
            $transactionId = null;

            if ($currentLicense && $currentLicense->subscription_id) {
                // Extract transaction_id from subscription_id if it's in format PADDLE-txn_xxx
                if (preg_match('/PADDLE-(txn_[a-zA-Z0-9]+)/', $currentLicense->subscription_id, $matches)) {
                    $transactionId = $matches[1];
                }
            }

            // If we don't have transaction_id, try to get it from recent orders
            if (!$transactionId) {
                $recentOrder = Order::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->first();

                if ($recentOrder && $recentOrder->transaction_id) {
                    $transactionId = $recentOrder->transaction_id;
                }
            }

            if (!$transactionId) {
                Log::error('No transaction_id found for Paddle upgrade at expiration', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId
                ]);
                return false;
            }

            Log::info('Found transaction_id for Paddle upgrade', [
                'user_id' => $user->id,
                'transaction_id' => $transactionId
            ]);

            // Get subscription_id from transaction using Paddle API
            $transactionResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/transactions/{$transactionId}");

            if (!$transactionResponse->successful()) {
                Log::error('Failed to get Paddle transaction details', [
                    'transaction_id' => $transactionId,
                    'response' => $transactionResponse->body()
                ]);
                return false;
            }

            $transactionData = $transactionResponse->json()['data'] ?? [];
            $realSubscriptionId = $transactionData['subscription_id'] ?? null;

            if (!$realSubscriptionId) {
                Log::error('No subscription_id found in Paddle transaction', [
                    'transaction_id' => $transactionId,
                    'transaction_data' => $transactionData
                ]);
                return false;
            }

            Log::info('Retrieved real subscription_id from Paddle transaction', [
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'real_subscription_id' => $realSubscriptionId
            ]);

            // Get current subscription details to find next billing date
            $subscriptionResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/subscriptions/{$realSubscriptionId}");

            if (!$subscriptionResponse->successful()) {
                Log::error('Failed to get Paddle subscription details for upgrade at expiration', [
                    'subscription_id' => $realSubscriptionId,
                    'response' => $subscriptionResponse->body()
                ]);
                return false;
            }

            $subscriptionData = $subscriptionResponse->json()['data'] ?? [];
            $nextBillingDate = $subscriptionData['next_billed_at'] ?? null;

            if (!$nextBillingDate) {
                Log::error('No next billing date found in Paddle subscription', [
                    'subscription_id' => $realSubscriptionId,
                    'subscription_data' => $subscriptionData
                ]);
                return false;
            }

            // Get the new price ID for the package
            $productsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Paddle products fetch failed for upgrade at expiration', [
                    'status' => $productsResponse->status()
                ]);
                return false;
            }

            $products = $productsResponse->json()['data'];
            $matchingProduct = collect($products)->firstWhere('name', $packageData->name);

            if (!$matchingProduct) {
                Log::error('Paddle product not found for upgrade at expiration', [
                    'package' => $packageData->name
                ]);
                return false;
            }

            $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
            if (!$price) {
                Log::error('No active prices found for upgrade at expiration', [
                    'product_id' => $matchingProduct['id']
                ]);
                return false;
            }

            // Create an order for the upgrade that will be processed at expiration
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending_upgrade',
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'order_type' => 'upgrade_at_expiration',
                'transaction_id' => 'PADDLE-UPGRADE-EXP-' . Str::random(10),
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'upgrade_to' => $packageData->name,
                    'upgrade_type' => 'at_expiration',
                    'next_billing_date' => $nextBillingDate,
                    'scheduled_upgrade' => true,
                    'new_price_id' => $price['id'],
                    'subscription_id' => $realSubscriptionId,
                    'original_transaction_id' => $transactionId
                ]
            ]);

            // $prorationBillingMode = it will be the date when current subscription before upgrade expires
            $prorationBillingMode = $nextBillingDate;

            // Schedule the upgrade using Paddle's API
            $updateResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->patch("{$apiBaseUrl}/subscriptions/{$realSubscriptionId}", [
                'items' => [['price_id' => $price['id'], 'quantity' => 1]],
                'proration_billing_mode' => $prorationBillingMode
            ]);

            if (!$updateResponse->successful()) {
                Log::error('Failed to schedule Paddle upgrade at expiration', [
                    'subscription_id' => $realSubscriptionId,
                    'response' => $updateResponse->body()
                ]);

                // Clean up the order we created
                $order->delete();
                return false;
            }

            Log::info('Paddle upgrade scheduled at expiration', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $realSubscriptionId,
                'next_billing_date' => $nextBillingDate,
                'order_id' => $order->id,
                'transaction_id' => $transactionId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Paddle upgrade at expiration error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'real_subscription_id' => $realSubscriptionId ?? null,
                'transaction_id' => $transactionId ?? null
            ]);
            return false;
        }
    }

    private function createPaddleUpgradeOrder($user, $packageData, $subscriptionId)
    {
        try {
            // Generate a temporary transaction ID for the upgrade order
            $tempTransactionId = 'PADDLE-UPGRADE-' . Str::random(10);

            // Create a new order for the upgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'order_type' => 'upgrade',
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'upgrade_to' => $packageData->name,
                    'upgrade_type' => 'subscription_upgrade',
                    'temp_transaction_id' => true,
                    'subscription_id' => $subscriptionId
                ]
            ]);

            // Generate Paddle checkout URL for upgrade
            $checkoutUrl = $this->generatePaddleUpgradeUrl($order, $packageData, $subscriptionId);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create Paddle upgrade order', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function generatePaddleUpgradeUrl($order, $packageData, $subscriptionId)
    {
        try {
            // Get the new price ID for the package
            $paddleClient = new \App\Services\PaddleClient();
            $product = $paddleClient->findProductByName($packageData->name);

            if (!$product) {
                Log::error('Paddle product not found for upgrade', [
                    'package' => $packageData->name
                ]);
                return null;
            }

            $price = $paddleClient->findActivePriceForProduct($product['id']);
            if (!$price) {
                Log::error('No active prices found for upgrade', [
                    'product_id' => $product['id']
                ]);
                return null;
            }

            // Get user from order
            $user = $order->user;

            // Generate Paddle checkout URL for upgrade
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $baseUrl = $environment === 'production'
                ? 'https://checkout.paddle.com'
                : 'https://sandbox-checkout.paddle.com';

            $checkoutUrl = "{$baseUrl}/pay/{$price['id']}?" . http_build_query([
                'customer_id' => $user->paddle_customer_id ?? '',
                'subscription_id' => $subscriptionId,
                'order_id' => $order->id,
                'passthrough' => json_encode([
                    'user_id' => $user->id,
                    'package' => $packageData->name,
                    'package_id' => $packageData->id,
                    'order_id' => $order->id,
                    'upgrade_type' => 'subscription_upgrade'
                ])
            ]);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to generate Paddle upgrade URL', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function handleFastSpringUpgrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting FastSpring upgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // Original immediate upgrade flow remains as fallback
            $checkoutUrl = $this->createFastSpringUpgradeOrder($user, $packageData, $subscriptionId);

            if (!$checkoutUrl) {
                Log::error('Failed to create FastSpring upgrade order', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return response()->json(['error' => 'Failed to create upgrade order'], 500);
            }

            Log::info('FastSpring upgrade order created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'message' => 'Upgrade order created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('FastSpring upgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Upgrade failed'], 500);
        }
    }

    private function createFastSpringUpgradeOrder($user, $packageData, $subscriptionId)
    {
        try {
            // Generate a temporary transaction ID for the upgrade order
            $tempTransactionId = 'FS-UPGRADE-' . Str::random(10);

            // Create a new order for the upgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                'order_type' => 'upgrade',
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'upgrade_to' => $packageData->name,
                    'upgrade_type' => 'subscription_upgrade',
                    'temp_transaction_id' => true,
                    'subscription_id' => $subscriptionId
                ]
            ]);

            // Generate FastSpring checkout URL for upgrade
            $checkoutUrl = $this->generateFastSpringUpgradeUrl($order, $packageData, $subscriptionId);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create FastSpring upgrade order', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function generateFastSpringUpgradeUrl($order, $packageData, $subscriptionId)
    {
        // Generate FastSpring upgrade URL
        // This would typically include the subscription ID and new package
        $baseUrl = config('payment.gateways.FastSpring.base_url', 'https://sbl.onfastspring.com');
        $storefront = config('payment.gateways.FastSpring.storefront', 'livebuzzstudio.test.onfastspring.com/popup-test-87654-payment');

        $upgradeUrl = "{$baseUrl}/{$storefront}?product={$packageData->name}&subscription={$subscriptionId}&order_id={$order->id}";

        return $upgradeUrl;
    }

    private function createPaddleUpgradeCheckout($user, $packageData, $subscriptionId, $priceId)
    {
        try {
            Log::info('Creating Paddle upgrade checkout', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId,
                'price_id' => $priceId
            ]);

            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for upgrade checkout');
                return null;
            }

            // Generate a temporary transaction ID for the upgrade order
            $tempTransactionId = 'PADDLE-UPGRADE-' . Str::random(10);

            // Create a new order for the upgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'order_type' => 'upgrade',
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'upgrade_to' => $packageData->name,
                    'upgrade_type' => 'subscription_upgrade',
                    'temp_transaction_id' => true,
                    'price_id' => $priceId,
                    'subscription_id' => $subscriptionId
                ]
            ]);

            $requestData = [
                'items' => [['price_id' => $priceId, 'quantity' => 1]],
                'customer_id' => $user->paddle_customer_id,
                'currency_code' => 'USD',
                'custom_data' => [
                    'user_id' => (string) $user->id,
                    'package_id' => (string) $packageData->id,
                    'package' => $packageData->name,
                    'order_id' => (string) $order->id,
                    'action' => 'upgrade'
                ],
                'proration_billing_mode' => 'prorated_immediately',
                'checkout' => [
                    'settings' => ['display_mode' => 'overlay'],
                    'success_url' => route('payments.success', [
                        'gateway' => 'paddle',
                        'transaction_id' => '{transaction_id}',
                        'upgrade' => 'true',
                        'order_id' => $order->id
                    ]),
                    'cancel_url' => route('payments.popup-cancel')
                ]
            ];

            // Create a checkout session for the upgrade
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$apiBaseUrl}/transactions", $requestData);

            if (!$response->successful()) {
                Log::error('Paddle upgrade checkout creation failed', [
                    'response' => $response->body(),
                    'status' => $response->status(),
                    'order_id' => $order->id
                ]);

                // Clean up the order we created
                $order->delete();
                return null;
            }

            $data = $response->json();
            $checkoutUrl = $data['data']['checkout']['url'] ?? null;

            if (!$checkoutUrl) {
                Log::error('No checkout URL in Paddle response', [
                    'response' => $data,
                    'order_id' => $order->id
                ]);

                // Clean up the order we created
                $order->delete();
                return null;
            }

            // Update order with real transaction ID and checkout URL
            $realTransactionId = $data['data']['id'] ?? null;
            if ($realTransactionId) {
                $order->update([
                    'transaction_id' => $realTransactionId,
                    'metadata' => array_merge($order->metadata ?? [], [
                        'paddle_transaction_id' => $realTransactionId,
                        'checkout_url' => $checkoutUrl,
                        'temp_transaction_id' => false
                    ])
                ]);
            } else {
                // Update order with checkout URL
                $order->update([
                    'metadata' => array_merge($order->metadata ?? [], [
                        'checkout_url' => $checkoutUrl
                    ])
                ]);
            }

            Log::info('Paddle upgrade checkout created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId,
                'transaction_id' => $realTransactionId ?? $tempTransactionId,
                'order_id' => $order->id,
                'checkout_url' => $checkoutUrl
            ]);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create Paddle upgrade checkout', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function handlePayProGlobalUpgrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting PayProGlobal upgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // For PayProGlobal upgrades, we need to create a new order
            // This will redirect to PayProGlobal's upgrade flow
            $checkoutUrl = $this->createPayProGlobalUpgradeOrder($user, $packageData, $subscriptionId);

            if (!$checkoutUrl) {
                Log::error('Failed to create PayProGlobal upgrade order', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return response()->json(['error' => 'Failed to create upgrade order'], 500);
            }

            Log::info('PayProGlobal upgrade order created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'message' => 'Upgrade order created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('PayProGlobal upgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Upgrade failed'], 500);
        }
    }

    private function createPayProGlobalUpgradeOrder($user, $packageData, $subscriptionId)
    {
        try {
            // Generate a temporary transaction ID for the upgrade order
            $tempTransactionId = 'PPG-UPGRADE-' . Str::random(10);

            // Create a new order for the upgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                'order_type' => 'upgrade',
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'upgrade_to' => $packageData->name,
                    'upgrade_type' => 'subscription_upgrade',
                    'temp_transaction_id' => true,
                    'subscription_id' => $subscriptionId
                ]
            ]);

            // Generate PayProGlobal checkout URL for upgrade
            $checkoutUrl = $this->generatePayProGlobalUpgradeUrl($order, $packageData, $subscriptionId);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create PayProGlobal upgrade order', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function generatePayProGlobalUpgradeUrl($order, $packageData, $subscriptionId)
    {
        // Generate PayProGlobal upgrade URL
        $baseUrl = config('payment.gateways.PayProGlobal.base_url', 'https://store.payproglobal.com');
        $merchantId = config('payment.gateways.PayProGlobal.merchant_id', '');

        $upgradeUrl = "{$baseUrl}/checkout?merchant_id={$merchantId}&product={$packageData->name}&subscription={$subscriptionId}&order_id={$order->id}&upgrade=true";

        return $upgradeUrl;
    }

    public function downgradeSubscription(Request $request)
    {
        Log::info('Package downgrade requested', ['user_id' => Auth::id()]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Block further plan changes if an upgrade is currently active
            if (!$this->licenseService->canUserChangePlan($user)) {
                return response()->json([
                    'error' => 'Plan Change Restricted',
                    'message' => 'You already have an active upgraded plan. Further upgrades or changes are not allowed until this plan expires.',
                    'action' => 'info'
                ], 403);
            }

            $newPackage = $request->input('package');
            if (!$newPackage) {
                return response()->json(['error' => 'Package name is required'], 400);
            }

            // Process package name to lowercase
            $processedPackage = $this->processPackageName($newPackage);

            // Validate the new package
            $packageData = Package::where('name', ucfirst($processedPackage))->first();
            if (!$packageData) {
                return response()->json([
                    'error' => 'Invalid Package',
                    'message' => 'The selected package is not available or doesn\'t exist. Please choose a valid package.',
                    'action' => 'select_valid_package'
                ], 400);
            }

            // Check if user has an active subscription first
            if (!$user->is_subscribed) {
                return response()->json([
                    'error' => 'Subscription Required',
                    'message' => 'You need an active subscription to downgrade your package. Please purchase a subscription first.',
                    'action' => 'purchase_subscription'
                ], 400);
            }

            // Get the current active license to get the subscription_id
            $currentLicense = $user->userLicence;
            if (!$currentLicense || !$currentLicense->subscription_id) {
                return response()->json([
                    'error' => 'License Configuration Issue',
                    'message' => 'Your software license is not properly configured for downgrades. This usually happens when your license details are missing or incomplete. Please contact our support team to verify and fix your license configuration.',
                    'action' => 'contact_support',
                    'details' => 'License record or subscription ID is missing'
                ], 400);
            }

            $subscriptionId = $currentLicense->subscription_id;

            Log::info('Starting package downgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'package_price' => $packageData->price,
                'current_subscription_id' => $subscriptionId
            ]);

            // Check if user has a payment gateway
            if (!$user->payment_gateway_id) {
                return response()->json([
                    'error' => 'Payment Method Missing',
                    'message' => 'No payment method is associated with your subscription. Please contact support to resolve this issue.',
                    'action' => 'contact_support'
                ], 400);
            }

            $gateway = $user->paymentGateway;
            if (!$gateway) {
                return response()->json([
                    'error' => 'Payment Method Not Found',
                    'message' => 'We couldn\'t find your payment method details. Please contact support to resolve this issue.',
                    'action' => 'contact_support'
                ], 400);
            }

            // Handle downgrade based on gateway
            if ($gateway->name === 'Paddle') {
                // Additional validation for Paddle downgrades
                if (!$user->paddle_customer_id) {
                    // Try to create or retrieve Paddle customer ID
                    $paddleCustomerId = $this->ensurePaddleCustomerId($user);
                    if (!$paddleCustomerId) {
                        return response()->json([
                            'error' => 'Paddle Customer ID Missing',
                            'message' => 'Your Paddle customer information is missing. Please contact support to resolve this issue.',
                            'action' => 'contact_support'
                        ], 400);
                    }
                }
                return $this->handlePaddleDowngrade($user, $packageData, $subscriptionId);
            } elseif ($gateway->name === 'FastSpring') {
                $fastSpringClient = new FastSpringClient(
                    config('payment.gateways.FastSpring.username'),
                    config('payment.gateways.FastSpring.password')
                );
                return $fastSpringClient->downgradeSubscription($user, $subscriptionId, $packageData->name);
            } elseif ($gateway->name === 'Pay Pro Global') {
                return $this->handlePayProGlobalDowngrade($user, $packageData, $subscriptionId);
            } else {
                return response()->json([
                    'error' => 'Payment Method Not Supported',
                    'message' => 'Your current payment method doesn\'t support package downgrades. Please contact support for assistance.',
                    'action' => 'contact_support'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Package downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'error' => 'Downgrade Failed',
                'message' => 'We encountered an error while processing your downgrade. Please try again or contact support if the problem persists.',
                'action' => 'retry_or_contact_support'
            ], 500);
        }
    }

    private function handlePaddleDowngrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting Paddle downgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId,
                'paddle_customer_id' => $user->paddle_customer_id
            ]);

            // Use PaddleClient service to get product and price information
            $paddleClient = new PaddleClient();
            Log::info('Fetching Paddle product for downgrade', ['package_name' => $packageData->name]);

            $product = $paddleClient->findProductByName($packageData->name);

            if (!$product) {
                Log::error('Paddle product not found for downgrade', ['package' => $packageData->name]);
                return response()->json(['error' => 'Unavailable package'], 400);
            }

            Log::info('Paddle product found for downgrade', [
                'product_id' => $product['id'],
                'product_name' => $product['name']
            ]);

            Log::info('Fetching active price for product', ['product_id' => $product['id']]);
            $price = $paddleClient->findActivePriceForProduct($product['id']);
            if (!$price) {
                Log::error('No active prices found for downgrade', ['product_id' => $product['id']]);
                return response()->json(['error' => 'No active price'], 400);
            }

            Log::info('Active price found for downgrade', [
                'price_id' => $price['id'],
                'price_amount' => $price['unit_price']['amount'] ?? 'unknown'
            ]);

            // For Paddle downgrades, we need to create a checkout session
            // This will create a popup checkout for the downgrade
            Log::info('Creating Paddle downgrade checkout', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'price_id' => $price['id'],
                'subscription_id' => $subscriptionId
            ]);

            $checkoutUrl = $this->createPaddleDowngradeCheckout($user, $packageData, $subscriptionId, $price['id']);

            if (!$checkoutUrl) {
                Log::error('Failed to create Paddle downgrade checkout', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'price_id' => $price['id']
                ]);
                return response()->json(['error' => 'Failed to create downgrade checkout'], 500);
            }

            // Extract transaction ID from the checkout URL or use the order's transaction ID
            $order = Order::where('user_id', $user->id)
                ->where('order_type', 'downgrade')
                ->where('package_id', $packageData->id)
                ->latest()
                ->first();

            $transactionId = $order ? $order->transaction_id : null;

            Log::info('Paddle downgrade checkout created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'checkout_url' => $checkoutUrl,
                'transaction_id' => $transactionId
            ]);

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'checkout_url' => $checkoutUrl,
                'message' => 'Downgrade checkout created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return response()->json(['error' => 'Downgrade failed'], 500);
        }
    }

    private function createPaddleDowngradeCheckout($user, $packageData, $subscriptionId, $priceId)
    {
        try {
            Log::info('Creating Paddle downgrade checkout', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId,
                'price_id' => $priceId
            ]);

            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for downgrade checkout');
                return null;
            }

            // Generate a temporary transaction ID for the downgrade order
            $tempTransactionId = 'PADDLE-DOWNGRADE-' . Str::random(10);

            // Create a new order for the downgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'order_type' => 'downgrade',
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => ($user->userLicence && $user->userLicence->package) ? $user->userLicence->package->name : ($user->package->name ?? 'Unknown'),
                    'downgrade_to' => $packageData->name,
                    'downgrade_type' => 'subscription_downgrade',
                    'temp_transaction_id' => true,
                    'price_id' => $priceId,
                    'subscription_id' => $subscriptionId
                ]
            ]);

            // Ensure user has a Paddle customer ID
            if (!$user->paddle_customer_id) {
                Log::error('Paddle customer ID missing for downgrade checkout', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                $order->delete();
                return null;
            }

            $requestData = [
                'items' => [['price_id' => $priceId, 'quantity' => 1]],
                'customer_id' => $user->paddle_customer_id,
                'currency_code' => 'USD',
                'custom_data' => [
                    'user_id' => (string) $user->id,
                    'package_id' => (string) $packageData->id,
                    'package' => $packageData->name,
                    'order_id' => (string) $order->id,
                    'action' => 'downgrade'
                ],
                'proration_billing_mode' => 'prorated_immediately',
                'checkout' => [
                    'settings' => ['display_mode' => 'overlay'],
                    'success_url' => route('payments.success', [
                        'gateway' => 'paddle',
                        'transaction_id' => '{transaction_id}',
                        'downgrade' => 'true',
                        'order_id' => $order->id
                    ]),
                    'cancel_url' => route('payments.popup-cancel')
                ]
            ];

            // Create a checkout session for the downgrade
            Log::info('Making Paddle API request for downgrade checkout', [
                'api_url' => "{$apiBaseUrl}/transactions",
                'request_data' => $requestData
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$apiBaseUrl}/transactions", $requestData);

            if (!$response->successful()) {
                Log::error('Paddle downgrade checkout creation failed', [
                    'response' => $response->body(),
                    'status' => $response->status(),
                    'order_id' => $order->id
                ]);

                // Clean up the order we created
                $order->delete();
                return null;
            }

            $data = $response->json();
            $checkoutUrl = $data['data']['checkout']['url'] ?? null;

            if (!$checkoutUrl) {
                Log::error('No checkout URL in Paddle downgrade response', [
                    'response' => $data,
                    'order_id' => $order->id
                ]);

                // Clean up the order we created
                $order->delete();
                return null;
            }

            // Update order with real transaction ID and checkout URL
            $realTransactionId = $data['data']['id'] ?? null;
            if ($realTransactionId) {
                $order->update([
                    'transaction_id' => $realTransactionId,
                    'metadata' => array_merge($order->metadata ?? [], [
                        'paddle_transaction_id' => $realTransactionId,
                        'checkout_url' => $checkoutUrl,
                        'temp_transaction_id' => false
                    ])
                ]);
            } else {
                // Update order with checkout URL
                $order->update([
                    'metadata' => array_merge($order->metadata ?? [], [
                        'checkout_url' => $checkoutUrl
                    ])
                ]);
            }

            Log::info('Paddle downgrade checkout created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId,
                'transaction_id' => $realTransactionId ?? $tempTransactionId,
                'order_id' => $order->id,
                'checkout_url' => $checkoutUrl
            ]);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create Paddle downgrade checkout', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function handleFastSpringDowngrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting FastSpring downgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // For FastSpring downgrades, we use the FastSpring popup flow
            $fastSpringClient = new FastSpringClient(
                config('payment.gateways.FastSpring.username'),
                config('payment.gateways.FastSpring.password')
            );

            $result = $fastSpringClient->downgradeSubscription($user, $subscriptionId, $packageData->name);

            if (!$result['success']) {
                Log::error('Failed to prepare FastSpring downgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
                return response()->json(['error' => $result['message'] ?? 'Failed to prepare downgrade'], 500);
            }

            Log::info('FastSpring downgrade prepared successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // Return a dummy checkout URL since the frontend expects one
            // The actual FastSpring popup will be handled by the frontend
            return response()->json([
                'success' => true,
                'checkout_url' => 'javascript:void(0)', // Dummy URL to satisfy frontend
                'requires_popup' => true,
                'package_name' => $packageData->name,
                'action' => 'downgrade',
                'message' => 'Preparing downgrade checkout...'
            ]);
        } catch (\Exception $e) {
            Log::error('FastSpring downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Downgrade failed'], 500);
        }
    }

    private function createFastSpringDowngradeOrder($user, $packageData, $subscriptionId)
    {
        try {
            // Generate a temporary transaction ID for the downgrade order
            $tempTransactionId = 'FS-DOWNGRADE-' . Str::random(10);

            // Create a new order for the downgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                'order_type' => 'downgrade',
                'subscription_id' => $subscriptionId,
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'downgrade_to' => $packageData->name,
                    'downgrade_type' => 'subscription_downgrade',
                    'temp_transaction_id' => true
                ]
            ]);

            // Generate FastSpring checkout URL for downgrade
            $checkoutUrl = $this->generateFastSpringDowngradeUrl($order, $packageData, $subscriptionId);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create FastSpring downgrade order', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function generateFastSpringDowngradeUrl($order, $packageData, $subscriptionId)
    {
        // Generate FastSpring downgrade URL
        $baseUrl = config('payment.gateways.FastSpring.base_url', 'https://sbl.onfastspring.com');
        $storefront = config('payment.gateways.FastSpring.storefront', 'livebuzzstudio.test.onfastspring.com/popup-test-87654-payment');

        $downgradeUrl = "{$baseUrl}/{$storefront}?product={$packageData->name}&subscription={$subscriptionId}&order_id={$order->id}&downgrade=true";

        return $downgradeUrl;
    }

    private function handlePayProGlobalDowngrade($user, $packageData, $subscriptionId)
    {
        try {
            Log::info('Starting PayProGlobal downgrade process', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'subscription_id' => $subscriptionId
            ]);

            // For PayProGlobal downgrades, we need to create a new order
            // This will redirect to PayProGlobal's downgrade flow
            $checkoutUrl = $this->createPayProGlobalDowngradeOrder($user, $packageData, $subscriptionId);

            if (!$checkoutUrl) {
                Log::error('Failed to create PayProGlobal downgrade order', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
                return response()->json(['error' => 'Failed to create downgrade order'], 500);
            }

            Log::info('PayProGlobal downgrade order created successfully', [
                'user_id' => $user->id,
                'package_name' => $packageData->name,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'message' => 'Downgrade order created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('PayProGlobal downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Downgrade failed'], 500);
        }
    }

    private function createPayProGlobalDowngradeOrder($user, $packageData, $subscriptionId)
    {
        try {
            // Generate a temporary transaction ID for the downgrade order
            $tempTransactionId = 'PPG-DOWNGRADE-' . Str::random(10);

            // Create a new order for the downgrade
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->getEffectivePrice(),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                'order_type' => 'downgrade',
                'subscription_id' => $subscriptionId,
                'transaction_id' => $tempTransactionId,
                'metadata' => [
                    'original_package' => $user->package->name ?? 'Unknown',
                    'downgrade_to' => $packageData->name,
                    'downgrade_type' => 'subscription_downgrade',
                    'temp_transaction_id' => true
                ]
            ]);

            // Generate PayProGlobal checkout URL for downgrade
            $checkoutUrl = $this->generatePayProGlobalDowngradeUrl($order, $packageData, $subscriptionId);

            return $checkoutUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create PayProGlobal downgrade order', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'package_name' => $packageData->name
            ]);
            return null;
        }
    }

    private function generatePayProGlobalDowngradeUrl($order, $packageData, $subscriptionId)
    {
        // Generate PayProGlobal downgrade URL
        $baseUrl = config('payment.gateways.PayProGlobal.base_url', 'https://store.payproglobal.com');
        $merchantId = config('payment.gateways.PayProGlobal.merchant_id', '');

        $downgradeUrl = "{$baseUrl}/checkout?merchant_id={$merchantId}&product={$packageData->name}&subscription={$subscriptionId}&order_id={$order->id}&downgrade=true";

        return $downgradeUrl;
    }

    private function updateTemporaryTransactionId($order, $realTransactionId)
    {
        try {
            Log::info('Updating transaction ID', [
                'old_transaction_id' => $order->transaction_id,
                'new_transaction_id' => $realTransactionId,
                'order_id' => $order->id
            ]);

            // Update the order with the real transaction ID
            $order->update([
                'transaction_id' => $realTransactionId
            ]);

            Log::info('Successfully updated transaction ID', [
                'order_id' => $order->id,
                'new_transaction_id' => $realTransactionId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update transaction ID', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'real_transaction_id' => $realTransactionId
            ]);
            return false;
        }
    }

    public function verifyOrder(Request $request, string $transactionId)
    {
        Log::info('[verifyOrder] called', [
            'transaction_id' => $transactionId,
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);
        Log::info('=== ORDER VERIFICATION STARTED ===', [
            'transaction_id' => $transactionId,
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                Log::error('User not authenticated for order verification', ['transaction_id' => $transactionId]);
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            Log::info('User authenticated for verification', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'transaction_id' => $transactionId
            ]);

            // First check if we already have a completed order
            $order = Order::where('transaction_id', $transactionId)
                ->where('user_id', $user->id)
                ->first();

            if ($order) {
                Log::info('Existing order found in database', [
                    'order_id' => $order->id,
                    'order_status' => $order->status,
                    'transaction_id' => $transactionId,
                    'user_id' => $user->id
                ]);
            } else {
                Log::info('No existing order found in database', [
                    'transaction_id' => $transactionId,
                    'user_id' => $user->id
                ]);
            }

            if ($order && $order->status === 'completed') {
                Log::info('Order already completed in database', [
                    'transaction_id' => $transactionId,
                    'order_id' => $order->id
                ]);
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Order already processed'
                ]);
            }

            // Verify with Paddle API
            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for order verification');
                return response()->json(['error' => 'Payment configuration error'], 500);
            }

            Log::info('Verifying transaction with Paddle API', [
                'transaction_id' => $transactionId,
                'api_endpoint' => "{$apiBaseUrl}/transactions/{$transactionId}"
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey
            ])->get("{$apiBaseUrl}/transactions/{$transactionId}");

            if (!$response->successful()) {
                Log::error('Paddle transaction verification failed', [
                    'transaction_id' => $transactionId,
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return response()->json(['error' => 'Transaction verification failed'], 400);
            }

            $transactionData = $response->json()['data'];

            Log::info('Paddle API response received', [
                'transaction_id' => $transactionId,
                'transaction_status' => $transactionData['status'],
                'transaction_amount' => $transactionData['details']['totals']['total'] ?? null,
                'custom_data' => $transactionData['custom_data'] ?? null
            ]);

            // Check if transaction is completed/paid
            if (!in_array($transactionData['status'], ['completed', 'paid'])) {
                Log::info('Transaction not yet completed', [
                    'transaction_id' => $transactionId,
                    'status' => $transactionData['status']
                ]);
                return response()->json([
                    'success' => false,
                    'status' => $transactionData['status'],
                    'message' => 'Transaction not yet completed'
                ]);
            }

            // Process the payment if not already processed
            if (!$order || $order->status !== 'completed') {
                Log::info('Processing payment - order needs to be created/updated', [
                    'transaction_id' => $transactionId,
                    'existing_order_id' => $order->id ?? null,
                    'existing_order_status' => $order->status ?? null
                ]);

                $customData = $transactionData['custom_data'] ?? [];
                $packageName = $customData['package'] ?? null;

                Log::info('Extracted custom data from transaction', [
                    'transaction_id' => $transactionId,
                    'custom_data' => $customData,
                    'package_name' => $packageName
                ]);

                if (!$packageName) {
                    Log::error('No package name in transaction custom data', [
                        'transaction_id' => $transactionId,
                        'custom_data' => $customData
                    ]);
                    return response()->json(['error' => 'Invalid transaction data'], 400);
                }

                Log::info('Calling processPaddlePaymentFromWebhook', [
                    'transaction_id' => $transactionId,
                    'package_name' => $packageName,
                    'user_id' => $user->id
                ]);

                // Process the payment using existing logic
                $result = $this->processPaddlePaymentFromWebhook($transactionData, $packageName, $user->id);

                if ($result) {
                    Log::info('Order processed successfully during verification', [
                        'transaction_id' => $transactionId,
                        'user_id' => $user->id,
                        'result' => $result
                    ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'completed',
                        'message' => 'Order processed successfully'
                    ]);
                } else {
                    Log::error('Failed to process order during verification', [
                        'transaction_id' => $transactionId,
                        'user_id' => $user->id,
                        'result' => $result
                    ]);
                    return response()->json(['error' => 'Order processing failed'], 500);
                }
            }

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'message' => 'Order verified successfully'
            ]);
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
        Log::info('Payment cancelled', [
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
            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            $config = [
                'api_key_exists' => !empty($apiKey),
                'api_key_length' => strlen($apiKey ?? ''),
                'environment' => $environment,
                'api_base_url' => $apiBaseUrl,
                'api_url_config' => config('payment.gateways.Paddle.api_url')
            ];

            if (empty($apiKey)) {
                return response()->json([
                    'error' => 'Paddle API key not configured',
                    'config' => $config
                ], 500);
            }

            // Test API connection
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)->get("{$apiBaseUrl}/products");

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'config' => $config,
                'products_count' => $response->successful() ? count($response->json()['data'] ?? []) : 0,
                'products' => $response->successful() ? collect($response->json()['data'] ?? [])->pluck('name')->toArray() : []
            ]);
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

    /**
     * Ensure Paddle customer ID exists for the user
     */
    private function ensurePaddleCustomerId($user)
    {
        try {
            Log::info('Ensuring Paddle customer ID exists', [
                'user_id' => $user->id,
                'email' => $user->email,
                'current_paddle_customer_id' => $user->paddle_customer_id
            ]);

            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for customer ID creation');
                return null;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];

            // First, try to find existing customer by email
            $existingCustomerResponse = Http::withHeaders($headers)
                ->get("{$apiBaseUrl}/customers", ['email' => $user->email]);

            if ($existingCustomerResponse->successful()) {
                $customers = $existingCustomerResponse->json()['data'] ?? [];
                if (!empty($customers)) {
                    $existingCustomer = $customers[0]; // Take the first matching customer
                    $user->update(['paddle_customer_id' => $existingCustomer['id']]);

                    Log::info('Found existing Paddle customer', [
                        'user_id' => $user->id,
                        'paddle_customer_id' => $existingCustomer['id']
                    ]);
                    return $existingCustomer['id'];
                }
            }

            // If no existing customer found, create a new one
            Log::info('Creating new Paddle customer', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]);

            $customerData = [
                'email' => $user->email,
                'name' => $user->name ?: ($user->first_name && $user->last_name ? $user->first_name . ' ' . $user->last_name : 'User'),
                'custom_data' => ['user_id' => (string) $user->id]
            ];

            // Ensure name is not empty
            if (empty($customerData['name']) || trim($customerData['name']) === '') {
                $customerData['name'] = 'User';
            }

            $customerResponse = Http::withHeaders($headers)->post("{$apiBaseUrl}/customers", $customerData);

            if (!$customerResponse->successful()) {
                $responseData = $customerResponse->json();

                // Check if customer already exists
                if (
                    $customerResponse->status() === 409 &&
                    isset($responseData['error']['code']) &&
                    $responseData['error']['code'] === 'customer_already_exists'
                ) {
                    Log::info('Paddle customer already exists, extracting customer ID', [
                        'user_id' => $user->id,
                        'response' => $responseData
                    ]);

                    // Extract customer ID from the error message
                    $customerId = null;
                    if (isset($responseData['error']['detail'])) {
                        // The detail contains: "customer email conflicts with customer of id ctm_01k0q9qrqyxr4g23cy6y0921wg"
                        if (preg_match('/customer of id ([a-zA-Z0-9_]+)/', $responseData['error']['detail'], $matches)) {
                            $customerId = $matches[1];
                        }
                    }

                    if ($customerId) {
                        // Save the existing customer ID
                        $user->update(['paddle_customer_id' => $customerId]);

                        Log::info('Paddle customer ID saved from existing customer', [
                            'user_id' => $user->id,
                            'paddle_customer_id' => $customerId
                        ]);
                        return $customerId;
                    }
                }

                Log::error('Failed to create Paddle customer', [
                    'user_id' => $user->id,
                    'status' => $customerResponse->status(),
                    'response' => $customerResponse->body()
                ]);
                return null;
            }

            // Customer was created successfully
            $customerData = $customerResponse->json();
            if (!isset($customerData['data']['id'])) {
                Log::error('Paddle customer creation response missing customer ID', [
                    'response' => $customerData
                ]);
                return null;
            }

            $user->update(['paddle_customer_id' => $customerData['data']['id']]);

            Log::info('Paddle customer created successfully', [
                'user_id' => $user->id,
                'paddle_customer_id' => $user->paddle_customer_id
            ]);

            return $customerData['data']['id'];
        } catch (\Exception $e) {
            Log::error('Error ensuring Paddle customer ID', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return null;
        }
    }

    /**
     * Update the subscription in Paddle to change the product/price
     */
    private function updatePaddleSubscription($subscriptionId, $package, $user)
    {
        try {
            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for subscription update');
                return false;
            }

            // Get the new price ID for the package
            $productsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Failed to fetch Paddle products for subscription update', [
                    'status' => $productsResponse->status(),
                    'response' => $productsResponse->body()
                ]);
                return false;
            }

            $products = $productsResponse->json()['data'];
            $matchingProduct = collect($products)->firstWhere('name', $package->name);

            if (!$matchingProduct) {
                Log::error('Paddle product not found for subscription update', ['package' => $package->name]);
                return false;
            }

            $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
            if (!$price) {
                Log::error('No active prices found for subscription update', ['product_id' => $matchingProduct['id']]);
                return false;
            }

            // Update the subscription with the new price
            $updateData = [
                'items' => [
                    [
                        'price_id' => $price['id'],
                        'quantity' => 1
                    ]
                ],
                'proration_billing_mode' => 'prorated_immediately'
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->patch("{$apiBaseUrl}/subscriptions/{$subscriptionId}", $updateData);

            if ($response->successful()) {
                Log::info('Paddle subscription updated successfully', [
                    'subscription_id' => $subscriptionId,
                    'package_name' => $package->name,
                    'price_id' => $price['id'],
                    'user_id' => $user->id
                ]);
                return true;
            } else {
                Log::error('Failed to update Paddle subscription', [
                    'subscription_id' => $subscriptionId,
                    'package_name' => $package->name,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error updating Paddle subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'package_name' => $package->name ?? 'Unknown',
                'user_id' => $user->id ?? null
            ]);
            return false;
        }
    }

    public function payproglobalDowngrade(Request $request)
    {
        Log::info('[payproglobalDowngrade] called', ['user_id' => Auth::id(), 'params' => $request->all()]);

        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                if (!$user) {
                    return response()->json(['error' => 'User not authenticated.'], 401);
                }

                $targetPackageId = $request->input('package_id');
                $targetPackage = Package::find($targetPackageId);
                $currentPackage = $user->package;

                if (!$targetPackage || !$currentPackage) {
                    Log::error('PayProGlobal Downgrade: Invalid target or current package', [
                        'user_id' => $user->id,
                        'target_package_id' => $targetPackageId,
                        'current_package_id' => $currentPackage->id ?? 'N/A'
                    ]);
                    return response()->json(['error' => 'Invalid package selection.'], 400);
                }

                // Ensure it's a valid downgrade
                if ($targetPackage->price >= $currentPackage->price) {
                    Log::error('PayProGlobal Downgrade: Target package is not a downgrade', [
                        'user_id' => $user->id,
                        'target_package' => $targetPackage->name,
                        'current_package' => $currentPackage->name
                    ]);
                    return response()->json(['error' => 'Selected package is not a downgrade.'], 400);
                }

                // Check for an active license to get expiration date
                $activeLicense = $user->userLicence;
                if (!$activeLicense || !$activeLicense->expires_at) {
                    Log::error('PayProGlobal Downgrade: No active license found for user', ['user_id' => $user->id]);
                    return response()->json(['error' => 'No active subscription found to downgrade.'], 400);
                }

                // Create a pending order for the scheduled downgrade
                $pendingOrderId = 'PPG-DOWNGRADE-' . uniqid();
                $pendingOrder = Order::create([
                    'user_id' => $user->id,
                    'package_id' => $targetPackage->id,
                    'order_type' => 'downgrade',
                    'status' => 'scheduled_downgrade',
                    'transaction_id' => $pendingOrderId,
                    'amount' => $targetPackage->getEffectivePrice(),
                    'currency' => 'USD',
                    'payment_method' => $user->paymentGateway->name ?? 'Pay Pro Global',
                    'metadata' => [
                        'original_package_id' => $currentPackage->id,
                        'original_package_name' => $currentPackage->name,
                        'scheduled_activation_date' => $activeLicense->expires_at->toDateTimeString(),
                        'downgrade_processed' => false,
                    ]
                ]);

                // Build custom data for PayProGlobal
                $custom = json_encode([
                    'user_id' => $user->id,
                    'package_id' => $targetPackage->id,
                    'package' => $targetPackage->name,
                    'pending_order_id' => $pendingOrder->transaction_id,
                    'action' => 'downgrade',
                    'original_package_name' => $currentPackage->name,
                    'scheduled_activation_date' => $activeLicense->expires_at->toDateTimeString(),
                ]);

                $redirectUrl = route('payments.success', [
                    'gateway' => 'payproglobal',
                    'user_id' => $user->id,
                    'package' => $targetPackage->name,
                    'popup' => 'true',
                    'pending_order_id' => $pendingOrder->transaction_id,
                    'action' => 'downgrade'
                ]);

                Log::info('PayProGlobal downgrade successfully initiated, redirecting...', [
                    'user_id' => $user->id,
                    'pending_order_id' => $pendingOrderId,
                    'redirect_url' => $redirectUrl,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Your downgrade has been successfully scheduled.',
                    'redirect_url' => $redirectUrl
                ]);
            });

        } catch (\Exception $e) {
            Log::error('PayProGlobal downgrade checkout failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to initiate downgrade checkout.'], 500);
        }
    }

    /**
     * Manually complete a PayProGlobal pending order (for testing in local development)
     * This simulates what happens when PayProGlobal redirects to the success URL
     */
    public function manualCompletePayProGlobalOrder($pendingOrderId)
    {
        try {
            // Only allow in non-production environments
            if (app()->environment('production')) {
                return response()->json(['error' => 'This endpoint is not available in production'], 403);
            }

            Log::info('Manual PayProGlobal order completion requested', [
                'pending_order_id' => $pendingOrderId
            ]);

            $pendingOrder = Order::where('transaction_id', $pendingOrderId)->first();

            if (!$pendingOrder) {
                return response()->json(['error' => 'Pending order not found'], 404);
            }

            if ($pendingOrder->status === 'completed') {
                return response()->json(['message' => 'Order is already completed', 'order' => $pendingOrder], 200);
            }

            $user = User::find($pendingOrder->user_id);
            $package = Package::find($pendingOrder->package_id);

            if (!$user || !$package) {
                return response()->json(['error' => 'User or package not found'], 404);
            }

            DB::transaction(function () use ($user, $package, $pendingOrder) {
                $paymentGateway = PaymentGateways::where('name', 'Pay Pro Global')->first();
                if (!$paymentGateway) {
                    throw new \Exception('PayProGlobal gateway not configured.');
                }

                // Update order status
                $pendingOrder->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'transaction_id' => $pendingOrder->transaction_id . '-MANUAL',
                    'metadata' => array_merge(($pendingOrder->metadata ?? []), [
                        'manually_completed_at' => now()->toDateTimeString(),
                        'manual_completion' => true
                    ]),
                ]);

                // Update user subscription
                $user->update([
                    'package_id' => $package->id,
                    'is_subscribed' => true,
                    'payment_gateway_id' => $paymentGateway->id,
                    'subscription_id' => null // Reverting to null temporarily
                ]);

                // Create and activate license
                $this->licenseService->createAndActivateLicense(
                    $user,
                    $package,
                    null,
                    $paymentGateway->id
                );

                Log::info('PayProGlobal webhook: Order completed and license created', [
                    'order_id' => $pendingOrder->id,
                    'user_id' => $user->id,
                    'package' => $package->name,
                    'order_status' => $pendingOrder->status
                ]);
            });

            $pendingOrder->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Order completed successfully',
                'order' => [
                    'id' => $pendingOrder->id,
                    'status' => $pendingOrder->status,
                    'transaction_id' => $pendingOrder->transaction_id
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Manual PayProGlobal order completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to complete order: ' . $e->getMessage()], 500);
        }
    }
}
