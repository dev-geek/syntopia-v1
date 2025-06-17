<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\Package;
use App\Models\User;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    private function validatePackageAndGetUser($package)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();

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

    private function getProductIds($gateway)
    {
        $packages = Package::all()->keyBy(function ($item) {
            return strtolower($item->name);
        });

        $productMappings = [
            'free' => null,
            'starter' => null,
            'pro' => null,
            'business' => null,
            'enterprise' => null,
        ];

        $configuredIds = config("payment.gateways.{$gateway}.product_ids", []);

        foreach ($productMappings as $packageName => &$value) {
            if ($packageName === 'free' || $packageName === 'enterprise') {
                continue;
            }

            if (isset($packages[$packageName])) {
                $value = $packages[$packageName]->{"{$gateway}_product_id"}
                    ?? $configuredIds[$packageName]
                    ?? null;
            } else {
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

            $processedPackage = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            $userName = trim($user->name);
            if (empty($userName)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Missing name',
                    'message' => 'Full name is required for purchases'
                ], 400);
            }

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

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

            $priceId = reset($activePrices)['id'];
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
                'custom_data' => [
                    'user_id' => (string) $user->id,
                    'package' => $processedPackage
                ]
            ];

            $response = Http::withHeaders($headers)
                ->post('https://sandbox-api.paddle.com/transactions', $transactionData);

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

            // Process transaction data directly
            if (isset($transaction['checkout']['url'])) {
                // Store transaction details
                $this->savePaymentDetails($request->merge([
                    'transaction_id' => $transaction['id'],
                    'checkout_url' => $transaction['checkout']['url'],
                    'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                    'package_id' => $packageData->id,
                    'status' => 'pending'
                ]));
            }

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
            $apiHeaders = $headers ?: [
                'Authorization' => 'Bearer ' . config('payment.gateways.Paddle.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $baseUrl = 'https://sandbox-api.paddle.com';

            $searchResponse = Http::withHeaders($apiHeaders)
                ->get($baseUrl . '/customers', [
                    'email' => $user->email
                ]);

            if ($searchResponse->successful()) {
                $customers = $searchResponse->json()['data'] ?? [];
                if (!empty($customers)) {
                    return $customers[0]['id'];
                }
            }

            $customerResponse = Http::withHeaders($apiHeaders)
                ->post($baseUrl . '/customers', [
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

            $errorData = $customerResponse->json();
            if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'customer_already_exists') {
                if (preg_match('/customer of id (ctm_[a-zA-Z0-9]+)/', $errorData['error']['detail'] ?? '', $matches)) {
                    return $matches[1];
                }
            }

            throw new \Exception('Paddle API error: ' . ($errorData['error']['detail'] ?? $customerResponse->body()));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function validateCustomerId(string $id): bool
    {
        return preg_match('/^ctm_[a-zA-Z0-9]+$/', $id) === 1;
    }

    public function handlePaddleWebhook(Request $request)
    {
        $payload = $request->all();

        // Verify webhook signature
        $secret = config('payment.gateways.Paddle.webhook_secret');
        $signature = $request->header('Paddle-Signature');

        if ($secret && $signature) {
            [$timestamp, $hmac] = explode(';', str_replace('ts=', '', $signature));
            $computedHmac = hash_hmac('sha256', $timestamp . ':' . $request->getContent(), $secret);
            if (!hash_equals($hmac, $computedHmac)) {
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        switch ($payload['event_type'] ?? null) {
            case 'checkout.completed':
                return $this->handlePaddleTransactionCompleted($payload);
            case 'checkout.payment_failed':
                return $this->handlePaddleTransactionFailed($payload);
            default:
                return response()->json(['status' => 'ignored']);
        }
    }

    private function handlePaddleTransactionCompleted($payload)
    {
        try {
            $transactionId = $payload['data']['id'] ?? null;
            $userId = $payload['data']['custom_data']['user_id'] ?? null;
            $package = $payload['data']['custom_data']['package'] ?? null;

            if (!$transactionId || !$userId || !$package) {
                return response()->json(['error' => 'Missing required data'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();
            if (!$packageModel) {
                return response()->json(['error' => 'Package not found'], 404);
            }

            $this->activateUserSubscription($user, $package, 'Paddle');

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function handlePaddleTransactionFailed($payload)
    {
        try {
            $transactionId = $payload['data']['id'] ?? null;
            $userId = $payload['data']['custom_data']['user_id'] ?? null;
            $package = $payload['data']['custom_data']['package'] ?? null;

            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                }
            }

            return response()->json(['status' => 'processed_failed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function fastspringCheckout(Request $request, $package)
    {
        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            $productId = config("payment.gateways.FastSpring.product_ids.{$processedPackage}");

            if ($productId === null) {
                if ($processedPackage === 'free') {
                    try {
                        return response()->json([
                            'success' => true,
                            'message' => 'Free plan can be activated',
                            'package_details' => [
                                'name' => $packageData->name,
                                'price' => $packageData->price,
                                'duration' => $packageData->duration,
                                'features' => is_string($packageData->features)
                                    ? json_decode($packageData->features, true) ?? []
                                    : (array) $packageData->features
                            ]
                        ]);
                    } catch (\Exception $e) {
                        return response()->json(['error' => 'Failed to activate free plan'], 500);
                    }
                } elseif ($processedPackage === 'enterprise') {
                    return response()->json([
                        'checkoutUrl' => url('/contact/enterprise'),
                        'package_details' => [
                            'name' => $packageData->name,
                            'price' => $packageData->price,
                            'duration' => $packageData->duration,
                            'features' => is_string($packageData->features)
                                ? json_decode($packageData->features, true) ?? []
                                : (array) $packageData->features
                        ]
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'error' => 'Product ID not configured',
                    'message' => "Product ID not found for package: {$processedPackage}"
                ], 400);
            }

            $storefront = config('payment.gateways.FastSpring.storefront');
            if (!$storefront) {
                throw new \Exception('FastSpring storefront not configured');
            }

            $secureHash = hash_hmac(
                'sha256',
                $user->id . $processedPackage . time(),
                config('payment.gateways.FastSpring.webhook_secret', '')
            );

            $checkoutUrl = "https://{$storefront}/{$productId}";

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
                    'features' => is_string($packageData->features)
                        ? json_decode($packageData->features, true) ?? []
                        : (array) $packageData->features
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Failed to process FastSpring checkout',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function payProGlobalCheckout(Request $request, $package)
    {
        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            if ($processedPackage === 'free') {
                return response()->json([
                    'success' => true,
                    'message' => 'Free plan can be activated',
                    'package_details' => [
                        'name' => $packageData->name,
                        'price' => $packageData->price,
                        'duration' => $packageData->duration,
                        'features' => is_string($packageData->features)
                            ? json_decode($packageData->features, true) ?? []
                            : (array) $packageData->features
                    ]
                ]);
            }

            if ($processedPackage === 'enterprise') {
                return response()->json([
                    'checkoutUrl' => url('/contact/enterprise'),
                    'package_details' => [
                        'name' => $packageData->name,
                        'price' => $packageData->price,
                        'duration' => $packageData->duration,
                        'features' => is_string($packageData->features)
                            ? json_decode($packageData->features, true) ?? []
                            : (array) $packageData->features
                    ]
                ]);
            }

            $productIds = $this->getProductIds('PayProGlobal');
            $productId = $productIds[$processedPackage] ?? null;

            if (!$productId) {
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
                'package_id' => $packageData->id,  // Add package_id here
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => is_string($packageData->features)
                        ? json_decode($packageData->features, true) ?? []
                        : (array) $packageData->features
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Checkout processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleSuccess(Request $request)
    {
        try {
            $gateway = null;
            $orderId = null;

            if ($request->isMethod('post')) {
                // Handle POST request (from FastSpring)
                $validated = $request->validate([
                    'gateway' => 'required|string|in:fastspring,paddle,payproglobal',
                    'orderId' => 'required|string',
                    'package_id' => 'sometimes|integer',
                    'payment_gateway_id' => 'sometimes|integer',
                ]);
                $gateway = $validated['gateway'];
                $orderId = $validated['orderId'];
                $packageId = $validated['package_id'] ?? null;
                $paymentGatewayId = $validated['payment_gateway_id'] ?? null;
            } elseif ($request->isMethod('get')) {
                // Handle GET request (from PayProGlobal)
                $gateway = $request->query('gateway');
                $orderId = $request->query('order_id');
                if (!$gateway || !$orderId) {
                    return redirect()->route('subscriptions.index')->with('error', 'Missing required parameters');
                }
                // No package_id or payment_gateway_id from PayProGlobal
            } else {
                return redirect()->route('subscriptions.index')->with('error', 'Invalid request method');
            }

            switch (strtolower($gateway)) {
                case 'fastspring':
                    return $this->handleFastSpringSuccess($request, $orderId);
                case 'payproglobal':
                    $user = User::where('last_payment_id', $orderId)->first();

                    if ($user && $user->package_id && $user->subscription_ends_at && $user->subscription_ends_at->isFuture()) {
                        return redirect()->route('user.dashboard')->with('success', 'Your payment was successful and your subscription is active!');
                    } elseif ($user) {
                        return redirect()->route('user.dashboard')->with('info', 'Your payment is being processed. Your subscription will be activated shortly.');
                    } else {
                        return redirect()->route('user.dashboard')->with('info', 'Your payment is being processed. Your subscription will be activated shortly.');
                    }
                default:
                    return redirect()->route('user.dashboard')->with('success', 'Payment completed successfully');
            }
        } catch (\Exception $e) {
            return redirect()->route('subscriptions.index')->with('error', 'Payment verification failed');
        }
    }

    private function handleFastSpringSuccess(Request $request, $orderId)
    {
        $apiUsername = config('payment.gateways.FastSpring.username');
        $apiPassword = config('payment.gateways.FastSpring.password');

        if (!$apiUsername || !$apiPassword) {
            return redirect()->route('subscriptions.index')->with('error', 'Payment completed but verification failed');
        }

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $user->update([
                'payment_gateway_id' => $request->payment_gateway_id,
                'package_id' => $request->package_id,
                'subscription_starts_at' => now()
            ]);

            return redirect()->route('user.dashboard')->with('success', 'Payment completed successfully! Your account has been upgraded.');
        } catch (\Exception $e) {
            return redirect()->route('subscriptions.index')->with('error', 'Payment completed but verification failed');
        }
    }

    public function handleCancel(Request $request)
    {
        try {
            $passthrough = json_decode($request->passthrough ?? '{}', true);

            $cancellationData = [
                'checkout_id' => $request->checkout_id,
                'package' => $passthrough['package'] ?? null,
                'retry_url' => $passthrough['checkout_url'] ?? route('subscriptions.index')
            ];

            $user = auth()->user() ?? User::find($passthrough['user_id'] ?? null);

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
            return redirect()->route('subscriptions.index')->with(
                'error',
                'Your payment was cancelled. Please try again or contact support.'
            );
        }
    }

    private function activateUserSubscription($user, $package, $gatewayName)
    {

        DB::transaction(function () use ($user, $package, $gatewayName) {
            $paymentGatewayId = $this->getPaymentGatewayId($gatewayName);

            $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();
            if (!$packageModel) {
                throw new \Exception("Package not found: {$package}");
            }

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
                    $subscriptionEndsAt = now()->addMonth();
            }

            $updateData = [
                'package_id' => $packageModel->id,
                'payment_gateway_id' => $paymentGatewayId,
                'subscription_starts_at' => now(),
                'subscription_ends_at' => $subscriptionEndsAt,
            ];

            $user->update($updateData);
        });
    }

    public function handleFastSpringWebhook(Request $request)
    {
        $payload = $request->all();

        $secret = config('payment.gateways.FastSpring.webhook_secret');
        $signature = $request->header('X-FS-Signature');

        if ($secret && $signature) {
            $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($signature, $computedSignature)) {
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        switch ($payload['type'] ?? null) {
            case 'order.completed':
            case 'subscription.activated':
            case 'subscription.charge.completed':
                return $this->handleFastSpringOrderCompleted($payload);
            default:
                return response()->json(['status' => 'ignored']);
        }
    }

    private function handleFastSpringOrderCompleted($payload)
    {
        try {
            $orderId = $payload['id'] ?? $payload['order'] ?? null;

            $userId = null;
            $package = null;

            if (isset($payload['tags'])) {
                $tags = is_string($payload['tags']) ? json_decode($payload['tags'], true) : $payload['tags'];
                $userId = $tags['user_id'] ?? null;
                $package = $tags['package'] ?? null;
            }

            if (!$userId && isset($payload['contact']['email'])) {
                $user = User::where('email', $payload['contact']['email'])->first();
                $userId = $user ? $user->id : null;
            }

            if (!$package && isset($payload['items'][0]['product'])) {
                $package = strtolower($payload['items'][0]['product']);
            }

            if (!$userId || !$package) {
                return response()->json(['error' => 'Missing required data'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $this->activateUserSubscription($user, $package, 'fastspring');

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        $payload = $request->all();

        $secretKey = Config::get('payment.gateways.PayProGlobal.webhook_secret');
        $signature = $request->header('X-PayPro-Signature'); // Placeholder header

        $paymentSuccessful = false;
        $paymentStatusField = 'payment_status'; // Placeholder
        $successStatusValue = 'completed'; // Placeholder

        if (isset($payload[$paymentStatusField]) && $payload[$paymentStatusField] === $successStatusValue) {
            $paymentSuccessful = true;
        }

        if ($paymentSuccessful) {
            try {
                $customData = null;
                if (isset($payload['custom']) && is_string($payload['custom'])) {
                    $customData = json_decode($payload['custom'], true);
                } elseif (isset($payload['custom'])) {
                    $customData = (array) $payload['custom'];
                }

                $userId = $customData['user_id'] ?? null;
                $package = $customData['package'] ?? null;

                if (!$userId || !$package) {
                    return response()->json(['error' => 'Missing required data for processing'], 400);
                }

                $user = User::find($userId);
                if (!$user) {
                    return response()->json(['error' => 'User not found'], 404);
                }

                $this->activateUserSubscription($user, $package, 'PayProGlobal');
                return response()->json(['status' => 'processed']);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Processing failed internally'], 500);
            }
        } else {
            return response()->json(['status' => 'ignored_unsuccessful_or_unrecognized']);
        }
    }

    public function savePaymentDetails(Request $request)
    {
        try {
            $request->validate([
                'payment_gateway_id' => 'required|exists:payment_gateways,id',
                'package_id' => 'required'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $user->update([
            'payment_gateway_id' => $request->payment_gateway_id,
            'package_id' => $request->package_id,
            'subscription_starts_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Payment details saved successfully']);
    }

    public function getOrdersList()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Cache payment gateway lookup for 1 hour
        $paymentGateway = Cache::remember("user_{$user->id}_payment_gateway", 3600, function () use ($user) {
            return PaymentGateways::where('id', $user->payment_gateway_id)->first()->name;
        });

        $client = new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5]); // Set timeouts
        $orders = [];

        try {
            if ($paymentGateway === 'Pay Pro Global') {
                $vendorAccountId = config('payment.gateways.PayProGlobal.merchant_id');
                $apiSecretKey = config('payment.gateways.PayProGlobal.webhook_secret');

                // Batch fetch orders with pagination support
                $cacheKey = "paypro_orders_{$user->id}";
                $ordersData = Cache::remember($cacheKey, 300, function () use ($client, $vendorAccountId, $apiSecretKey) {
                    $response = $client->post('https://store.payproglobal.com/api/Orders/GetList', [
                        'json' => [
                            'vendorAccountId' => $vendorAccountId,
                            'apiSecretKey' => $apiSecretKey,
                            'dateFormat' => 'a',
                            'pageSize' => 50, // Assuming API supports pagination
                        ],
                        'headers' => [
                            'accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ],
                    ]);
                    return json_decode($response->getBody(), true);
                });

                $orderIds = array_column($ordersData['response'] ?? [], 'orderId');

                // Batch fetch order details if API supports it, otherwise optimize loop
                foreach (array_chunk($orderIds, 10) as $chunk) { // Process in chunks
                    $promises = [];
                    foreach ($chunk as $orderId) {
                        $promises[$orderId] = $client->postAsync('https://store.payproglobal.com/api/Orders/GetOrderDetails', [
                            'json' => [
                                'orderId' => $orderId,
                                'dateFormat' => 'a',
                                'vendorAccountId' => $vendorAccountId,
                                'apiSecretKey' => $apiSecretKey,
                            ],
                            'headers' => [
                                'accept' => 'application/json',
                                'Content-Type' => 'application/json',
                            ],
                        ]);
                    }

                    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
                    foreach ($responses as $orderId => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $orderDetails = json_decode($result['value']->getBody(), true);
                            $order = $orderDetails['response'] ?? null;

                            if ($order && $orderDetails['isSuccess']) {
                                $orders[] = [
                                    'id' => $order['orderId'],
                                    'package' => $order['orderItems'][0]['orderItemName'] ?? 'N/A',
                                    'amount' => $order['billingTotalPrice'] ?? 0.00,
                                    'payment' => $order['orderStatusName'] === 'Processed' ? 'Yes' : 'Pending',
                                    'created_at' => \Carbon\Carbon::parse($order['createdAt']),
                                ];
                            }
                        }
                    }
                }
            } elseif ($paymentGateway === 'FastSpring') {
                $username = config('payment.gateways.FastSpring.username');
                $password = config('payment.gateways.FastSpring.password');
                $auth = 'Basic ' . base64_encode($username . ':' . $password);

                // Cache orders list for 5 minutes
                $cacheKey = "fastspring_orders_{$user->id}";
                $ordersData = Cache::remember($cacheKey, 300, function () use ($client, $auth) {
                    $response = $client->get('https://api.fastspring.com/orders?limit=50&page=1', [
                        'headers' => [
                            'accept' => 'application/json',
                            'Authorization' => $auth,
                        ],
                    ]);
                    return json_decode($response->getBody(), true);
                });

                $orderIds = $ordersData['orders'] ?? [];

                // Batch fetch order details
                foreach (array_chunk($orderIds, 10) as $chunk) {
                    $promises = [];
                    foreach ($chunk as $orderId) {
                        $promises[$orderId] = $client->getAsync("https://api.fastspring.com/orders/{$orderId}", [
                            'headers' => [
                                'accept' => 'application/json',
                                'Authorization' => $auth,
                            ],
                        ]);
                    }

                    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
                    foreach ($responses as $orderId => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $orderDetails = json_decode($result['value']->getBody(), true);
                            $orders[] = [
                                'id' => $orderDetails['id'],
                                'package' => $orderDetails['items'][0]['display'] ?? 'N/A', // Corrected path
                                'amount' => $orderDetails['total'],
                                'payment' => $orderDetails['completed'] ? 'Yes' : 'Pending',
                                'created_at' => \Carbon\Carbon::parse($orderDetails['changed'] / 1000),
                            ];
                        }
                    }
                }
            } elseif ($paymentGateway === 'Paddle') {
                $apiKey = config('payment.gateways.Paddle.apiKey');

                // Cache transactions for 5 minutes
                $cacheKey = "paddle_orders_{$user->id}";
                $transactionsData = Cache::remember($cacheKey, 300, function () use ($client, $apiKey) {
                    $response = $client->get('https://sandbox-api.paddle.com/transactions', [
                        'headers' => [
                            'accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $apiKey,
                        ],
                        'query' => ['per_page' => 50],
                    ]);
                    return json_decode($response->getBody(), true);
                });

                $transactions = $transactionsData['data'] ?? [];

                // Batch fetch transaction details
                foreach (array_chunk($transactions, 10) as $chunk) {
                    $promises = [];
                    foreach ($chunk as $transaction) {
                        $transactionId = $transaction['id'];
                        $promises[$transactionId] = $client->getAsync("https://sandbox-api.paddle.com/transactions/{$transactionId}", [
                            'headers' => [
                                'accept' => 'application/json',
                                'Authorization' => 'Bearer ' . $apiKey,
                            ],
                        ]);
                    }

                    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
                    foreach ($responses as $transactionId => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $orderDetails = json_decode($result['value']->getBody(), true);
                            $orders[] = [
                                'id' => $orderDetails['id'],
                                'package' => $orderDetails['items'][0]['product']['name'] ?? 'N/A',
                                'amount' => $orderDetails['total'] ?? 0.00,
                                'payment' => $orderDetails['status'] === 'completed' ? 'Yes' : 'Pending',
                                'created_at' => \Carbon\Carbon::parse($orderDetails['created_at']),
                            ];
                        }
                    }
                }
            }

            return view('subscription.order', compact('orders', 'paymentGateway'));
        } catch (\Exception $e) {
            return view('subscription.order', compact('paymentGateway'))->with('error', 'Failed to fetch orders');
        }
    }
}
