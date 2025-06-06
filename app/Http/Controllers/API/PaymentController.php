<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\alert;

class PaymentController extends Controller
{
    private function validatePackageAndGetUser($package)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Get package data from database (case-insensitive)
        $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();

        // Check if package exists
        if (!$packageModel) {
            return response()->json([
                'error' => 'Invalid package',
                'message' => 'Package not found: ' . $package,
                'available_packages' => Package::pluck('name')->toArray()
            ], 400);
        }

        return [
            'user' => $user,
            'package' => $package,
            'packageData' => $packageModel
        ];
    }

    /**
     * Get the product IDs for the specified gateway
     *
     * @param string $gateway
     * @return array
     */
    private function getProductIds($gateway)
    {
        // First get all packages from database
        $packages = Package::all()->keyBy(function ($item) {
            return strtolower($item->name);
        });

        // Initialize the result array with all possible packages
        $productMappings = [
            'free' => null,
            'starter' => null,
            'pro' => null,
            'business' => null,
            'enterprise' => null,
        ];

        // Merge with configured product IDs from config
        $configuredIds = config("payment.gateways.{$gateway}.product_ids", []);

        foreach ($productMappings as $packageName => &$value) {
            // Skip free and enterprise packages as they don't need product IDs
            if ($packageName === 'free' || $packageName === 'enterprise') {
                continue;
            }

            // First try to get from package model if available
            if (isset($packages[$packageName])) {
                $value = $packages[$packageName]->{"{$gateway}_product_id"}
                    ?? $configuredIds[$packageName]
                    ?? null;
            } else {
                // Fallback to config only if package doesn't exist in DB
                $value = $configuredIds[$packageName] ?? null;
            }
        }

        return $productMappings;
    }

    private function getPaymentGatewayId($gatewayName)
    {
        $gatewayMappings = [
            'paddle' => 'Paddle',
            'fastspring' => 'FastSpring',
            'payproglobal' => 'Pay Pro Global',
            'payproGlobal' => 'Pay Pro Global'
        ];

        $normalizedName = $gatewayMappings[strtolower($gatewayName)] ?? $gatewayName;

        return DB::table('payment_gateways')
            ->where('name', $normalizedName)
            ->value('id');
    }

    /**
     * Generate a checkout URL for Paddle
     *
     * @param Request $request
     * @param string $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function paddleCheckout(Request $request, string $package)
    {
        try {
            $apiKey = config('payment.gateways.Paddle.api_key');

            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment configuration error',
                    'message' => 'Payment system is not properly configured'
                ], 500);
            }

            // Process package name
            $processedPackage = str_replace('-plan', '', strtolower($package));

            // Validate package and user
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation))
                return $validation;

            [$user, $packageData] = [$validation['user'], $validation['packageData']];

            // Validate user name
            $userName = trim($user->name);
            if (empty($userName)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Missing name',
                    'message' => 'Full name is required for purchases'
                ], 400);
            }

            // Standard headers for all Paddle API requests
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // Handle customer creation or lookup
            if (!$user->paddle_customer_id) {
                $customerId = $this->getOrCreatePaddleCustomer($user, $headers);
                if (!$customerId) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Customer setup failed',
                        'message' => 'Unable to create or find customer in payment system'
                    ], 500);
                }
                $user->paddle_customer_id = $customerId;
                $user->save();
            }

            $productsResponse = Http::withHeaders($headers)
                ->get('https://sandbox-api.paddle.com/products', [
                    'include' => 'prices',
                    'per_page' => 200,
                ]);

            if (!$productsResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Product fetch failed',
                    'message' => 'Could not retrieve product information'
                ], 500);
            }

            $products = $productsResponse->json()['data'];

            // Find matching product by name
            $matchingProduct = null;
            foreach ($products as $product) {
                if (strtolower($product['name']) === $processedPackage) {
                    $matchingProduct = $product;
                    break;
                }
            }

            if (!$matchingProduct) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unavailable package',
                    'message' => 'This package is not available for purchase',
                    'available_packages' => collect($products)->pluck('name')->toArray()
                ], 400);
            }

            // Check for active prices
            $prices = $matchingProduct['prices'] ?? [];
            $activePrices = array_filter($prices, function ($price) {
                return ($price['status'] ?? '') === 'active';
            });

            if (empty($activePrices)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active price',
                    'message' => 'This package has no active pricing'
                ], 400);
            }

            $priceId = $activePrices[0]['id'];

            // Prepare transaction data
            $transactionData = [
                'items' => [
                    [
                        'price_id' => $priceId,
                        'quantity' => 1
                    ]
                ],
                'customer_id' => $user->paddle_customer_id,
                'currency_code' => 'USD',
                'collection_mode' => 'automatic',
                'billing_details' => null,
            ];

            $response = Http::withHeaders($headers)
                ->post('https://sandbox-api.paddle.com/transactions', $transactionData);

            // Handle response
            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorData = json_decode($errorBody, true);

                $errorMessage = $errorData['error']['detail'] ?? 'Transaction creation failed';
                $errorCode = $errorData['error']['code'] ?? 'unknown_error';

                return response()->json([
                    'success' => false,
                    'error' => 'Payment gateway error',
                    'message' => $errorMessage,
                    'error_code' => $errorCode,
                    'details' => $errorData
                ], $response->status());
            }

            $transaction = $response->json()['data'];

            // Only update user data after successful payment
            return response()->json([
                'success' => true,
                'checkout_url' => $transaction['checkout']['url'] ?? null,
                'transaction_id' => $transaction['id']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Checkout failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getOrCreatePaddleCustomer($user, $headers)
    {
        try {
            // Use the incoming headers if they exist, otherwise fall back to config
            $apiHeaders = $headers ?: [
                'Authorization' => 'Bearer '.config('payment.gateways.Paddle.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            // Determine base URL based on key prefix
            $baseUrl = str_starts_with($apiHeaders['Authorization'], 'Bearer pdl_sdbx_')
                ? 'https://sandbox-api.paddle.com'
                : 'https://api.paddle.com';

            // Search for customer
            $searchResponse = Http::withHeaders($apiHeaders)
                ->get($baseUrl.'/customers', [
                    'email' => $user->email
                ]);

            if ($searchResponse->successful()) {
                $customers = $searchResponse->json()['data'] ?? [];
                if (!empty($customers)) {
                    return $customers[0]['id'];
                }
            }

            // Create new customer
            $customerResponse = Http::withHeaders($apiHeaders)
                ->post($baseUrl.'/customers', [
                    'email' => $user->email,
                    'name' => $user->name,
                    'custom_data' => [
                        'customer_reference_id' => (string) $user->id
                    ],
                    'locale' => 'en'
                ]);

            if ($customerResponse->successful()) {
                return $customerResponse->json()['data']['id'];
            }

            // Handle specific error cases
            $errorData = $customerResponse->json();
            if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'customer_already_exists') {
                if (preg_match('/customer of id (ctm_[a-zA-Z0-9]+)/', $errorData['error']['detail'] ?? '', $matches)) {
                    return $matches[1];
                }
            }

            throw new \Exception('Paddle API error: '.($errorData['error']['detail'] ?? $customerResponse->body()));

        } catch (\Exception $e) {
            Log::error('Paddle customer error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'headers' => $apiHeaders ?? null,
                'baseUrl' => $baseUrl ?? null
            ]);
            return null;
        }
    }
    private function validateCustomerId(string $id): bool
    {
        return preg_match('/^ctm_[a-zA-Z0-9]+$/', $id) === 1;
    }
    public function fastspringCheckout(Request $request, $package)
    {
        try {
            // Remove "-plan" suffix if present
            $processedPackage = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation; // Returns the error response
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // Get product ID from config
            $productId = config("payment.gateways.FastSpring.product_ids.{$processedPackage}");

            if ($productId === null) {
                if ($processedPackage === 'free') {
                    try {
                        // Only update user data for free plan after confirmation
                        return response()->json([
                            'success' => true,
                            'message' => 'Free plan can be activated',
                            'package_details' => [
                                'name' => $packageData->name,
                                'price' => $packageData->price,
                                'duration' => $packageData->duration,
                                'features' => json_decode($packageData->features)
                            ]
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Free plan activation failed: ' . $e->getMessage());
                        return response()->json(['error' => 'Failed to activate free plan'], 500);
                    }
                } elseif ($processedPackage === 'enterprise') {
                    return response()->json([
                        'checkoutUrl' => url('/contact/enterprise'),
                        'package_details' => [
                            'name' => $packageData->name,
                            'price' => $packageData->price,
                            'duration' => $packageData->duration,
                            'features' => json_decode($packageData->features)
                        ]
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'error' => 'Product ID not configured',
                    'message' => "Product ID not found for package: {$processedPackage}"
                ], 400);
            }

            // Get FastSpring storefront configuration
            $storefront = config('payment.gateways.FastSpring.storefront');
            if (!$storefront) {
                throw new \Exception('FastSpring storefront not configured');
            }

            // Create a secure hash for validating the order if your implementation requires it
            $secureHash = hash_hmac(
                'sha256',
                $user->id . $processedPackage . time(),
                config('payment.gateways.FastSpring.webhook_secret', '')
            );

            // Prepare the checkout URL
            $checkoutUrl = "https://{$storefront}/{$productId}";

            // Add query parameters
            $queryParams = [
                'referrer' => $user->id,
                'contactEmail' => $user->email,
                'contactFirstName' => $user->first_name ?? '',
                'contactLastName' => $user->last_name ?? '',
                'tags' => json_encode([
                    'user_id' => $user->id,
                    'package' => $processedPackage,
                    'secure_hash' => $secureHash
                ]),
                'returnUrl' => url('/all-subscriptions'),
                'cancelUrl' => route('payment.cancel') . '?source=fastspring',
            ];

            $checkoutUrl .= '?' . http_build_query($queryParams);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => json_decode($packageData->features)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('FastSpring checkout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to process FastSpring checkout',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a checkout URL for PayProGlobal
     *
     * @param Request $request
     * @param string $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function payProGlobalCheckout(Request $request, $package)
    {
        // try {
            // Normalize package name
            $processedPackage = str_replace('-plan', '', strtolower($package));

            // Validate package and user
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // Handle free plan
            if ($processedPackage === 'free') {
                return response()->json([
                    'success' => true,
                    'message' => 'Free plan can be activated',
                    'package_details' => [
                        'name' => $packageData->name,
                        'price' => $packageData->price,
                        'duration' => $packageData->duration,
                        'features' => is_string($packageData->features)
                            ? json_decode($packageData->features)
                            : $packageData->features
                    ]
                ]);
            }

            // Handle enterprise plan
            if ($processedPackage === 'enterprise') {
                return response()->json([
                    'checkoutUrl' => url('/contact/enterprise'),
                    'package_details' => [
                        'name' => $packageData->name,
                        'price' => $packageData->price,
                        'duration' => $packageData->duration,
                        'features' => is_string($packageData->features)
                            ? json_decode($packageData->features)
                            : $packageData->features
                    ]
                ]);
            }

            // Get product ID for paid plans using the centralized method
            $productIds = $this->getProductIds('PayProGlobal');
            $productId = $productIds[$processedPackage] ?? null;

            if (!$productId) {
                // Fallback to legacy config method
                $legacyProductIds = [
                    'starter' => Config::get('payment.gateways.PayProGlobal.product_id_starter'),
                    'pro' => Config::get('payment.gateways.PayProGlobal.product_id_pro'),
                    'business' => Config::get('payment.gateways.PayProGlobal.product_id_business'),
                ];
                $productId = $legacyProductIds[$processedPackage] ?? null;
            }

            if (!$productId) {
                throw new \Exception("Product ID not configured for package: {$processedPackage}");
            }

            $secretKey = Config::get('payment.gateways.PayProGlobal.webhook_secret');
            $testMode = Config::get('payment.gateways.PayProGlobal.test_mode', true);

            // Generate PayProGlobal checkout URL with popup support
            $successUrl = url("/api/payment/success?gateway=payproglobal&order_id={order_id}");
            $cancelUrl = url("/subscriptions");
            $checkoutUrl = "https://store.payproglobal.com/checkout?products[1][id]={$productId}";
            $checkoutUrl .= "&email=" . urlencode($user->email);
            $checkoutUrl .= "&products[0][id]=" . $productId;
            $checkoutUrl .= "&custom=" . urlencode(json_encode([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'package' => $processedPackage,
            ]));
            $checkoutUrl .= "&first_name=" . urlencode($user->first_name ?? '');
            $checkoutUrl .= "&last_name=" . urlencode($user->last_name ?? '');
            $checkoutUrl .= "&page-template=ID";
            $checkoutUrl .= "&use-test-mode=true";
            $checkoutUrl .= "&secret-key=" . urlencode($secretKey);
            $checkoutUrl .= "&success-url=" . urlencode($successUrl);
            $checkoutUrl .= "&cancel-url=" . urlencode($cancelUrl);

            return response()->json([
                'checkoutUrl' => $checkoutUrl,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => is_string($packageData->features)
                        ? json_decode($packageData->features)
                        : $packageData->features
                ]
            ]);


            $this->activateUserSubscription($user, $package, 'PayProGlobal');



        // } catch (\Exception $e) {
        //     Log::error("PayProGlobal checkout failed for {$processedPackage}: " . $e->getMessage());
        //     return response()->json([
        //         'error' => 'Checkout processing failed',
        //         'message' => $e->getMessage()
        //     ], 500);
        // }
    }

    public function handleSuccess(Request $request)
    {
        try {
            $validated = $request->validate([
                'gateway' => 'required|string|in:fastspring,paddle,payproglobal',
                'orderId' => 'required|string'
            ]);

            // If validation passes, continue with your logic
            $gateway = $validated['gateway'];
            $orderId = $validated['orderId'];

            if (!$gateway || !$orderId) {
                return redirect()->route('subscriptions.index')->with('error', 'Invalid payment confirmation');
            }

            // Log successful payment
            Log::info("Payment successful", [
                'gateway' => $gateway,
                'order_id' => $orderId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Different handling based on gateway
            switch (strtolower($gateway)) {
                case 'fastspring':
                    return $this->handleFastSpringSuccess($request, $orderId);
                    // Add cases for other gateways as needed
                case 'payproglobal':
                    Log::info("PayProGlobal success redirect handling.", ['order_id' => $orderId, 'ip' => $request->ip()]);
                    // Attempt to find the user by the orderId, assuming webhook has/will process it.
                    // Ensure you are using the correct field that your webhook sets as the 'orderId'.
                    $user = User::where('last_payment_id', $orderId)->first();

                    if ($user && $user->package_id && $user->subscription_ends_at && $user->subscription_ends_at->isFuture()) {
                        // User found and subscription seems active
                        Log::info("PayProGlobal success redirect: Subscription found active for order.", ['order_id' => $orderId, 'user_id' => $user->id]);
                        return redirect()->route('subscriptions.index')->with('success', 'Your payment was successful and your subscription is active!');
                    } elseif ($user) {
                        // User found, but subscription might not be fully active yet or an issue occurred
                        Log::warning("PayProGlobal success redirect: User found for order, but subscription not fully active. Webhook may be pending or there was an issue during activation.", ['order_id' => $orderId, 'user_id' => $user->id, 'package_id' => $user->package_id, 'sub_ends_at' => $user->subscription_ends_at]);
                        return redirect()->route('subscriptions.index')->with('info', 'Your payment is being processed. Your subscription will be activated shortly. Please refresh in a few moments or check your email.');
                    } else {
                        // No user found with this orderId yet. Webhook might be delayed or an issue.
                        Log::warning("PayProGlobal success redirect: No user found yet for order. Webhook may be pending or orderId mismatch.", ['order_id' => $orderId]);
                        return redirect()->route('subscriptions.index')->with('info', 'Your payment is being processed. Your subscription will be activated shortly. Please check your email or contact support if it does not update soon.');
                    }
                    // No break needed due to return statements
                default:
                    return redirect()->route('subscriptions.index')->with('success', 'Payment completed successfully');
            }
        } catch (\Exception $e) {
            Log::error('Payment success handling failed: ' . $e->getMessage());
            return redirect()->route('subscriptions.index')->with('error', 'Payment verification failed');
        }
    }

    private function handleFastSpringSuccess(Request $request, $orderId)
    {
        $apiUsername = config('payment.gateways.FastSpring.username');
        $apiPassword = config('payment.gateways.FastSpring.password');

        if (!$apiUsername || !$apiPassword) {
            Log::error('FastSpring API credentials missing');
            return redirect()->route('subscriptions.index')->with('error', 'Payment completed but verification failed');
        }

        try {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        // Update user's payment_gateway_id and package_id
        $user->update([
            'payment_gateway_id' => $request->payment_gateway_id,
            'package_id' => $request->package_id,
            'subscription_starts_at' => now()
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Payment completed successfully! Your account has been upgraded.');

        } catch (\Exception $e) {
            Log::error('FastSpring success handling failed: ' . $e->getMessage());
            return redirect()->route('subscriptions.index')->with('error', 'Payment completed but verification failed');
        }
    }

    public function handleCancel(Request $request)
    {
        try {
            // 1. Log cancellation with context
            $passthrough = json_decode($request->passthrough ?? '{}', true);

            Log::info('Checkout cancelled', [
                'checkout_id' => $request->checkout_id,
                'user_id' => $passthrough['user_id'] ?? null,
                'package' => $passthrough['package'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->header('referer')
            ]);

            // 2. Prepare cancellation data
            $cancellationData = [
                'checkout_id' => $request->checkout_id,
                'package' => $passthrough['package'] ?? null,
                'retry_url' => $passthrough['checkout_url'] ?? route('subscriptions.index')
            ];

            // 3. If logged in user, offer personalized options
            $user = auth()->user() ?? User::find($passthrough['user_id'] ?? null);

            // 4. Show cancellation page with helpful options
            return view('payment.cancel', [
                'reason' => $request->reason ?? 'payment_cancelled',
                'options' => [
                    'Try again' => $cancellationData['retry_url'],
                    'Choose different plan' => route('subscriptions.index'),
                ],
                'user' => $user,
                'contact_email' => config('app.support_email')
            ]);
        } catch (\Exception $e) {
            Log::error('Cancel handling failed: ' . $e->getMessage());
            return redirect()->route('subscriptions.index')->with(
                'error',
                'Your payment was cancelled. Please try again or contact support.'
            );
        }
    }

    private function activateUserSubscription($user, $package, $gatewayName)
    {
        Log::info('[DEBUG] activateUserSubscription: Method called.', ['user_id' => $user->id, 'package' => $package, 'orderId' => $orderId, 'gatewayName' => $gatewayName]);
        // dd('[DEBUG] activateUserSubscription: Entry point', ['user_object' => $user, 'package' => $package, 'orderId' => $orderId, 'gatewayName' => $gatewayName]); // UNCOMMENT TO DEBUG HERE

        DB::transaction(function () use ($user, $package, $gatewayName) {
            // Get payment gateway ID
            $paymentGatewayId = $this->getPaymentGatewayId($gatewayName);
            Log::info('[DEBUG] activateUserSubscription: Fetched paymentGatewayId.', ['paymentGatewayId' => $paymentGatewayId, 'gatewayName_passed' => $gatewayName]);
            // dd('[DEBUG] activateUserSubscription: paymentGatewayId', ['paymentGatewayId' => $paymentGatewayId, 'gatewayName_passed' => $gatewayName]); // UNCOMMENT TO DEBUG HERE

            // Get package model (handle case variations)
            $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();

            if (!$packageModel) {
                Log::error('[DEBUG] activateUserSubscription: Package model not found.', ['package_name_searched' => $package]);
                // dd('[DEBUG] activateUserSubscription: Package model not found', ['package_name_searched' => $package, 'packageModel' => $packageModel]); // UNCOMMENT TO DEBUG HERE
                throw new \Exception("Package not found: {$package}");
            }
            Log::info('[DEBUG] activateUserSubscription: Fetched packageModel.', ['packageModel_id' => $packageModel->id, 'packageModel_name' => $packageModel->name]);

            // Calculate subscription end date based on package duration
            $subscriptionEndsAt = now();
            switch (strtolower($packageModel->duration)) {
                case 'month':
                case 'monthly':
                    $subscriptionEndsAt = now()->addMonth();
                    break;
                case 'year':
                case 'yearly':
                case 'annual':
                    $subscriptionEndsAt = now()->addYear();
                    break;
                default:
                    $subscriptionEndsAt = now()->addMonth(); // Default to monthly
            }

            // Update user's subscription
            $updateData = [
                'package_id' => $packageModel->id,
                'payment_gateway_id' => $paymentGatewayId,
                'subscription_starts_at' => now(),
                'subscription_ends_at' => $subscriptionEndsAt,
            ];
            Log::info('[DEBUG] activateUserSubscription: Data prepared for user update.', ['user_id' => $user->id, 'updateData' => $updateData]);
            // dd('[DEBUG] activateUserSubscription: Data for user update', ['user_id' => $user->id, 'updateData' => $updateData, 'user_before_update' => $user->toArray()]); // UNCOMMENT TO DEBUG HERE

            $user->update($updateData);
            Log::info('[DEBUG] activateUserSubscription: User update executed.', ['user_id' => $user->id, 'wasChanged' => $user->wasChanged()]);
            // dd('[DEBUG] activateUserSubscription: User after update attempt', ['user_object_after_update' => $user->fresh()->toArray(), 'wasChanged' => $user->wasChanged()]); // UNCOMMENT TO DEBUG HERE

            Log::info('User subscription activated', [
                'user_id' => $user->id,
                'package' => $package,
                'expires_at' => $subscriptionEndsAt
            ]);
        });
    }

    public function handleFastSpringWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('FastSpring webhook received', ['type' => $payload['type'] ?? 'unknown']);

        // Verify the webhook signature if secret is configured
        $secret = config('payment.gateways.FastSpring.webhook_secret');
        $signature = $request->header('X-FS-Signature');

        if ($secret && $signature) {
            $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($signature, $computedSignature)) {
                Log::error('Invalid FastSpring webhook signature');
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        // Handle different webhook events
        switch ($payload['type'] ?? null) {
            case 'order.completed':
                return $this->handleFastSpringOrderCompleted($payload);
            case 'subscription.activated':
            case 'subscription.charge.completed':
                return $this->handleFastSpringOrderCompleted($payload);
            default:
                Log::info('FastSpring webhook ignored', ['type' => $payload['type'] ?? 'unknown']);
                return response()->json(['status' => 'ignored']);
        }
    }


    private function handleFastSpringOrderCompleted($payload)
    {
        try {
            $orderId = $payload['id'] ?? $payload['order'] ?? null;

            // Extract user and package info from various possible locations
            $userId = null;
            $package = null;

            // Check tags
            if (isset($payload['tags'])) {
                $tags = is_string($payload['tags']) ? json_decode($payload['tags'], true) : $payload['tags'];
                $userId = $tags['user_id'] ?? null;
                $package = $tags['package'] ?? null;
            }

            // Check contact email as fallback
            if (!$userId && isset($payload['contact']['email'])) {
                $user = User::where('email', $payload['contact']['email'])->first();
                $userId = $user ? $user->id : null;
            }

            // Extract package from items if not in tags
            if (!$package && isset($payload['items'][0]['product'])) {
                $package = strtolower($payload['items'][0]['product']);
            }

            if (!$userId || !$package) {
                Log::error('Missing required data in FastSpring webhook', [
                    'user_id' => $userId,
                    'package' => $package,
                    'payload_type' => $payload['type'] ?? 'unknown'
                ]);
                return response()->json(['error' => 'Missing required data'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found in FastSpring webhook', [
                    'user_id' => $userId
                ]);
                return response()->json(['error' => 'User not found'], 404);
            }

            // Reuse existing activation method
            $this->activateUserSubscription($user, $package, 'fastspring');

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('FastSpring webhook processing failed: ' . $e->getMessage(), [
                'payload_type' => $payload['type'] ?? 'unknown'
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('[DEBUG] PayProGlobalWebhook: Received payload.', ['payload' => $payload]);
        // dd('[DEBUG] PayProGlobalWebhook: Initial payload', $payload); // UNCOMMENT TO DEBUG HERE

        // 1. Verify the webhook signature
        $secretKey = Config::get('payment.gateways.PayProGlobal.webhook_secret');
        $signature = $request->header('X-PayPro-Signature'); // Placeholder header
        Log::info('[DEBUG] PayProGlobalWebhook: Signature details.', ['secretKey_exists' => !empty($secretKey), 'signature_received' => $signature]);
        // dd('[DEBUG] PayProGlobalWebhook: Signature details', ['secretKey_exists' => !empty($secretKey), 'signature_received' => $signature, 'secretKey_value_for_manual_check' => $secretKey]); // UNCOMMENT TO DEBUG HERE

        // 1. Verify the webhook signature
        $secretKey = Config::get('payment.gateways.PayProGlobal.webhook_secret');
        // IMPORTANT: Replace 'X-PayPro-Signature' with the actual header PayProGlobal uses.
        // IMPORTANT: You'll need to implement the actual signature verification logic
        // based on PayProGlobal's documentation. This often involves hashing the
        // raw request body with the secret key.
        $signature = $request->header('X-PayPro-Signature'); // Placeholder header

        if ($secretKey && $signature) {
            // Example: $computedSignature = hash_hmac('sha256', $request->getContent(), $secretKey);
            // if (!hash_equals($signature, $computedSignature)) {
            //     Log::error('Invalid PayProGlobal webhook signature');
            //     return response()->json(['error' => 'Invalid signature'], 403);
            // }
            Log::info('[DEBUG] PayProGlobalWebhook: Signature verification placeholder. Implement actual verification.');
            // dd('[DEBUG] PayProGlobalWebhook: After signature check (assuming valid or no secret)', $payload); // UNCOMMENT TO DEBUG HERE
        } elseif ($secretKey) {
            Log::warning('PayProGlobal webhook signature missing, but secret key is configured.');
            // Depending on your security policy, you might want to reject unsigned webhooks
            // return response()->json(['error' => 'Signature missing'], 403);
        }


        // 2. Determine payment success
        // IMPORTANT: Replace 'payment_status' with the actual field from PayProGlobal's payload
        // and 'completed' with the actual value indicating a successful payment.
        $paymentSuccessful = false;
        $paymentStatusField = 'payment_status'; // Placeholder - Confirm with PayProGlobal docs
        $successStatusValue = 'completed';    // Placeholder - Confirm with PayProGlobal docs
        Log::info('[DEBUG] PayProGlobalWebhook: Checking payment status.', ['field_to_check' => $paymentStatusField, 'expected_value_for_success' => $successStatusValue, 'actual_value_in_payload' => $payload[$paymentStatusField] ?? 'NOT_SET']);

        if (isset($payload[$paymentStatusField]) && $payload[$paymentStatusField] === $successStatusValue) {
            $paymentSuccessful = true;
            Log::info('[DEBUG] PayProGlobalWebhook: Payment determined as SUCCESSFUL.');
        } else {
            Log::info('[DEBUG] PayProGlobalWebhook: Payment determined as NOT successful or status unknown.', ['status_field_value' => $payload[$paymentStatusField] ?? 'NOT_SET']);
            Log::info('PayProGlobal payment not marked as successful in webhook.', [
                'status_field' => $payload['payment_status'] ?? 'N/A'
            ]);
        }

        if ($paymentSuccessful) {
            try {
                // 3. Extract necessary data
                // Assuming 'custom' field contains JSON string with user_id, package, package_id
                $customData = null;
                if (isset($payload['custom']) && is_string($payload['custom'])) {
                    $customData = json_decode($payload['custom'], true);
                } elseif (isset($payload['custom'])) { // if it's already an array/object
                    $customData = (array) $payload['custom'];
                }
                Log::info('[DEBUG] PayProGlobalWebhook: Extracted customData.', ['customData' => $customData]);

                $userId = $customData['user_id'] ?? null;
                $package = $customData['package'] ?? null; // package name e.g. "pro"

                if (!$userId || !$package) {
                    Log::error('Missing required data in PayProGlobal webhook for successful payment', [
                        'user_id' => $userId,
                        'package' => $package,
                        'custom_data' => $customData,
                        'payload' => $payload
                    ]);
                    return response()->json(['error' => 'Missing required data for processing'], 400);
                }

                $user = User::find($userId);
                if (!$user) {
                    Log::error('User not found from PayProGlobal webhook', ['user_id' => $userId]);
                    return response()->json(['error' => 'User not found'], 404);
                }


                $this->activateUserSubscription($user, $package, 'PayProGlobal');

                Log::info('PayProGlobal payment processed and subscription activated.', [
                    'user_id' => $userId,
                    'package' => $package,
                    'order_id' => $orderId
                ]);
                return response()->json(['status' => 'processed']);

            } catch (\Exception $e) {
                Log::error('PayProGlobal webhook processing failed for successful payment: ' . $e->getMessage(), [
                    'payload' => $payload,
                    'exception' => $e
                ]);
                return response()->json(['error' => 'Processing failed internally'], 500);
            }
        } else {
            // Handle other statuses if needed (e.g., failed, refunded)
            Log::info('PayProGlobal webhook received for non-successful or unrecognized payment status.', [
                'payload' => $payload
            ]);
            return response()->json(['status' => 'ignored_unsuccessful_or_unrecognized']);
        }
    }
    public function savePaymentDetails(Request $request)
    {
        $request->validate([
            'payment_gateway_id' => 'required|exists:payment_gateways,id',
            'package_id' => 'required'
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        // Update user's payment_gateway_id and package_id
        $user->update([
            'payment_gateway_id' => $request->payment_gateway_id,
            'package_id' => $request->package_id,
            'subscription_starts_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Payment details saved successfully']);
    }
}
