<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\{
    Package,
    User,
    PaymentGateways,
    Order,
};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

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

            // Get or create Paddle customer
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

            // Get product and price information
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

            // Create transaction with success URL that includes verification
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
                    'package' => $processedPackage,
                    'package_id' => (string) $packageData->id
                ],
                // Add success redirect URL
                'checkout' => [
                    'success_url' => route('payments.paddle.verify') . '?transaction_id={transaction_id}'
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

            // Create a pending order to track this transaction
            if (isset($transaction['id'])) {
                Order::create([
                    'user_id' => $user->id,
                    'package_id' => $packageData->id,
                    'amount' => $transaction['details']['totals']['total'] / 100, // Convert from cents
                    'currency' => $transaction['currency_code'],
                    'transaction_id' => $transaction['id'],
                    'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                    'status' => 'pending',
                    'metadata' => [
                        'checkout_url' => $transaction['checkout']['url'] ?? null
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'checkout_url' => $transaction['checkout']['url'] ?? null,
                'transaction_id' => $transaction['id']
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            $signatureParts = explode(';', $signature);
            $timestamp = null;
            $hmac = null;

            foreach ($signatureParts as $part) {
                if (strpos($part, 'ts=') === 0) {
                    $timestamp = substr($part, 3);
                } elseif (strpos($part, 'h1=') === 0) {
                    $hmac = substr($part, 3);
                }
            }

            if ($timestamp && $hmac) {
                $signedPayload = $timestamp . ':' . $request->getContent();
                $computedHmac = hash_hmac('sha256', $signedPayload, $secret);

                if (!hash_equals($hmac, $computedHmac)) {
                    Log::error('Invalid Paddle webhook signature');
                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }
        }

        Log::info('Paddle webhook received', [
            'event_type' => $payload['event_type'] ?? 'unknown',
            'event_id' => $payload['event_id'] ?? null
        ]);

        switch ($payload['event_type'] ?? null) {
            case 'transaction.completed':
            case 'transaction.paid':
                return $this->handlePaddleTransactionCompleted($payload);
            case 'transaction.payment_failed':
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
            $packageId = $payload['data']['custom_data']['package_id'] ?? null;

            Log::info('Processing Paddle transaction completed webhook', [
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'package_id' => $packageId
            ]);

            if (!$transactionId || !$userId || !$packageId) {
                Log::error('Missing required data in Paddle webhook');
                return response()->json(['error' => 'Missing required data'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found for Paddle webhook', ['user_id' => $userId]);
                return response()->json(['error' => 'User not found'], 404);
            }

            $package = Package::find($packageId);
            if (!$package) {
                Log::error('Package not found for Paddle webhook', ['package_id' => $packageId]);
                return response()->json(['error' => 'Package not found'], 404);
            }

            $order = Order::where('transaction_id', $transactionId)->first();
            if ($order && $order->status === 'completed') {
                Log::info('Paddle transaction already processed', ['transaction_id' => $transactionId]);
                return response()->json(['status' => 'already_processed']);
            }

            DB::beginTransaction();
            try {
                $order = Order::updateOrCreate(
                    ['transaction_id' => $transactionId],
                    [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'amount' => ($payload['data']['details']['totals']['total'] ?? 0) / 100,
                        'currency' => $payload['data']['currency_code'] ?? 'USD',
                        'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                        'status' => 'completed',
                        'metadata' => $payload['data']
                    ]
                );

                // Assign license only if not already assigned
                if (!$user->license_key) {
                    $licenseKey = $this->makeLicense();
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                        'package_id' => $package->id,
                        'subscription_starts_at' => now(),
                        'license_key' => $licenseKey,
                        'is_subscribed' => true,
                    ]);

                    // Call external API to add license
                    $this->addLicenseToExternalAPI($user, $licenseKey);
                } else {
                    Log::info('License already assigned to user', ['user_id' => $user->id]);
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                        'package_id' => $package->id,
                        'subscription_starts_at' => now(),
                        'is_subscribed' => true,
                    ]);
                }

                DB::commit();

                Log::info('Paddle webhook processed successfully', [
                    'transaction_id' => $transactionId,
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'license_key' => $user->license_key,
                ]);

                return response()->json(['status' => 'processed']);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Paddle webhook processing failed', [
                    'transaction_id' => $transactionId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Processing failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error in Paddle webhook handler', [
                'transaction_id' => $transactionId ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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

            $storefront = config('payment.gateways.FastSpring.storefront');
            if (!$storefront) {
                throw new \Exception('FastSpring storefront not configured');
            }

            $secureHash = hash_hmac(
                'sha256',
                $user->id . $processedPackage . time(),
                config('payment.gateways.FastSpring.webhook_secret', '')
            );

            // Get product ID from configuration
            $productIds = config('payment.gateways.FastSpring.product_ids', []);
            $productId = $productIds[$processedPackage] ?? null;

            if (!$productId) {
                throw new \Exception("Product ID not configured for package: {$processedPackage}");
            }

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
                'returnUrl' => route('payments.success') . '?gateway=fastspring',
                'cancelUrl' => route('payments.cancel'),
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

    public function payProGlobalCheckout(Request $request, string $package)
    {
        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

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

            // Updated success URL with popup=true
            $successUrl = route('payments.success') . "?gateway=payproglobal&order_id={order_id}&user_id={$user->id}&package={$processedPackage}&popup=true";
            // Updated cancel URL to a dedicated popup cancel route
            $cancelUrl = route('payments.popup-cancel');

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
            $checkoutUrl .= "&currency=USD";
            $checkoutUrl .= "&use-test-mode=" . ($testMode ? 'true' : 'false');
            $checkoutUrl .= "&secret-key=" . urlencode($secretKey);
            $checkoutUrl .= "&success-url=" . urlencode($successUrl);
            $checkoutUrl .= "&cancel-url=" . urlencode($cancelUrl);

            return response()->json([
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
                'package_id' => $packageData->id,
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
            Log::error('PayProGlobal checkout error: ' . $e->getMessage(), [
                'package' => $package,
                'exception' => $e
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Checkout processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleSuccess(Request $request)
    {
        Log::info('Payment success callback received', $request->all());

        try {
            // Handle both GET and POST requests
            $gateway = $request->input('gateway', $request->query('gateway'));

            if ($request->query('popup') === 'true' && $gateway === 'payproglobal') {
                return view('payment.popup-success');
            }

            if ($gateway === 'payproglobal') {
                $orderId = $request->input('order_id', $request->query('order_id'));
                $userId = $request->input('user_id', $request->query('user_id'));
                $packageName = $request->input('package', $request->query('package'));
                $paymentId = $request->input('payment_id', $request->query('payment_id'));
                $paymentGatewayId = $this->getPaymentGatewayId('payproglobal');

                Log::info('Processing PayProGlobal success callback', [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'package' => $packageName,
                    'payment_id' => $paymentId
                ]);
                $user = User::find($userId);
                if (!$user) {
                    Log::error('No authenticated user for PayProGlobal success callback');
                    return response()->json(['error' => 'User not found'], 404);
                }

                $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
                if (!$package) {
                    Log::error('Package not found: ' . $packageName);
                    return response()->json(['error' => 'Invalid package'], 400);
                }

                DB::beginTransaction();
                try {
                    $order = Order::updateOrCreate(
                        ['transaction_id' => $orderId],
                        [
                            'user_id' => $userId,
                            'package_id' => $package->id,
                            'amount' => 0,
                            'currency' => 'USD',
                            'payment_gateway_id' => $paymentGatewayId,
                            'status' => 'pending',
                        ]
                    );

                    // Assign license only if not already assigned
                    if (!$user->license_key) {
                        $licenseKey = $this->makeLicense();
                        $user->update([
                            'payment_gateway_id' => $paymentGatewayId,
                            'package_id' => $package->id,
                            'subscription_starts_at' => now(),
                            'license_key' => $licenseKey,
                            'is_subscribed' => true,
                        ]);

                        // Call external API to add license
                        $this->addLicenseToExternalAPI($user, $licenseKey);
                    } else {
                        Log::info('License already assigned to user', ['user_id' => $user->id]);
                        $user->update([
                            'payment_gateway_id' => $paymentGatewayId,
                            'package_id' => $package->id,
                            'subscription_starts_at' => now(),
                            'is_subscribed' => true,
                        ]);
                    }

                    $order->status = 'completed';
                    $order->save();

                    DB::commit();
                    return response()->json([
                        'status' => 'pending',
                        'message' => 'Thank you! Your payment is being processed. You’ll be notified once confirmed.',
                        'redirect' => route('user.dashboard')
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('PayProGlobal success callback failed', ['message' => $e->getMessage()]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment processing initiated, but an error occurred. Please contact support if issues persist.',
                        'redirect' => route('subscriptions.index')
                    ], 500);
                }
            } elseif ($gateway === 'paddle') {
                $transactionId = $request->input('transaction_id', $request->query('transaction_id'));

                Log::info('Processing Paddle success callback', [
                    'transaction_id' => $transactionId,
                    'request_method' => $request->method(),
                    'request_data' => $request->all()
                ]);

                if (!$transactionId) {
                    Log::error('Missing transaction_id in Paddle success callback');
                    return redirect()->route('subscriptions.index')
                        ->with('error', 'Invalid payment request. Please contact support.');
                }

                $user = Auth::user();
                if (!$user) {
                    Log::error('No authenticated user for Paddle success callback');
                    return redirect()->route('login')
                        ->with('error', 'Please log in to complete your purchase.');
                }

                // Check if order already processed by webhook
                $order = Order::where('transaction_id', $transactionId)->first();
                if ($order && $order->status === 'completed') {
                    Log::info('Paddle payment already processed', ['transaction_id' => $transactionId]);
                    return redirect()->route('user.dashboard')
                        ->with('success', 'Thank you for your payment! Your subscription is now active.');
                }

                // Verify transaction with Paddle API
                try {
                    $apiKey = config('payment.gateways.Paddle.api_key');
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Accept' => 'application/json',
                    ])->get("https://sandbox-api.paddle.com/transactions/{$transactionId}");

                    if (!$response->successful()) {
                        Log::error('Failed to verify Paddle transaction', [
                            'transaction_id' => $transactionId,
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                        return redirect()->route('subscriptions.index')
                            ->with('error', 'Payment verification failed.');
                    }

                    $transactionData = $response->json()['data'];
                    if ($transactionData['status'] !== 'completed' && $transactionData['status'] !== 'paid') {
                        Log::warning('Paddle transaction not completed', [
                            'transaction_id' => $transactionId,
                            'status' => $transactionData['status']
                        ]);
                        return redirect()->route('subscriptions.index')
                            ->with('error', 'Payment is not yet completed.');
                    }

                    $userId = $transactionData['custom_data']['user_id'] ?? null;
                    $packageId = $transactionData['custom_data']['package_id'] ?? null;

                    if ($userId != $user->id || !$packageId) {
                        Log::error('Invalid user or package in transaction data', [
                            'transaction_id' => $transactionId,
                            'user_id' => $userId,
                            'package_id' => $packageId
                        ]);
                        return redirect()->route('subscriptions.index')
                            ->with('error', 'Invalid payment data.');
                    }

                    $package = Package::find($packageId);
                    if (!$package) {
                        Log::error('Package not found with ID: ' . $packageId);
                        return redirect()->route('subscriptions.index')
                            ->with('error', 'Invalid package selected.');
                    }

                    DB::beginTransaction();
                    try {
                        // Create or update order
                        $order = Order::updateOrCreate(
                            ['transaction_id' => $transactionId],
                            [
                                'user_id' => $user->id,
                                'package_id' => $package->id,
                                'amount' => ($transactionData['details']['totals']['total'] ?? 0) / 100,
                                'currency' => $transactionData['currency_code'],
                                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                                'status' => 'completed',
                                'metadata' => $transactionData
                            ]
                        );

                        // Assign license only if not already assigned
                        if (!$user->license_key) {
                            $licenseKey = $this->makeLicense();
                            $user->update([
                                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                                'package_id' => $package->id,
                                'subscription_starts_at' => now(),
                                'license_key' => $licenseKey,
                                'is_subscribed' => true,
                            ]);

                            // Call external API to add license
                            $this->addLicenseToExternalAPI($user, $licenseKey);
                        } else {
                            Log::info('License already assigned to user', ['user_id' => $user->id]);
                            $user->update([
                                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                                'package_id' => $package->id,
                                'subscription_starts_at' => now(),
                                'is_subscribed' => true,
                            ]);
                        }

                        Log::info('Paddle payment processed successfully', [
                            'transaction_id' => $transactionId,
                            'user_id' => $user->id,
                            'package_id' => $package->id,
                            'license_key' => $user->license_key,
                        ]);

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Paddle success callback failed', [
                            'transaction_id' => $transactionId,
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return redirect()->route('subscriptions.index')
                            ->with('error', 'Something went wrong while processing your payment.');
                    }

                    return redirect()->route('user.dashboard')
                        ->with('success', 'Thank you for your payment! Your subscription is now active.');
                } catch (\Exception $e) {
                    Log::error('Paddle transaction verification error', [
                        'transaction_id' => $transactionId,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return redirect()->route('subscriptions.index')
                        ->with('error', 'Payment verification failed.');
                }
            } elseif ($gateway === 'fastspring') {
                $orderId = $request->input('orderId', $request->query('orderId'));
                $packageId = $request->input('package_id', $request->query('package_id'));
                $paymentGatewayId = $request->input('payment_gateway_id', $request->query('payment_gateway_id'));

                Log::info('Processing FastSpring success callback', [
                    'order_id' => $orderId,
                    'package_id' => $packageId,
                    'payment_gateway_id' => $paymentGatewayId,
                    'request_method' => $request->method()
                ]);

                $user = Auth::user();
                if (!$user) {
                    Log::error('No authenticated user for FastSpring success callback');
                    return redirect()->route('login')->with('error', 'Please log in to complete your purchase.');
                }

                $package = Package::find($packageId);
                if (!$package) {
                    Log::error('Package not found with ID: ' . $packageId);
                    return redirect()->route('subscriptions.index')
                        ->with('error', 'Invalid package selected. Please try again.');
                }

                DB::beginTransaction();
                try {
                    $order = Order::create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'amount' => $package->price,
                        'currency' => 'USD',
                        'transaction_id' => $orderId ?: 'FS-' . Str::random(10),
                        'payment_gateway_id' => $paymentGatewayId,
                        'status' => 'pending',
                    ]);

                    Log::info('Created pending order for FastSpring payment', [
                        'order_id' => $order->id,
                        'transaction_id' => $order->transaction_id,
                        'user_id' => $user->id,
                        'package_id' => $package->id
                    ]);

                    // Assign license only if not already assigned
                    if (!$user->license_key) {
                        $licenseKey = $this->makeLicense($user);
                        $user->update([
                            'payment_gateway_id' => $paymentGatewayId,
                            'package_id' => $packageId,
                            'subscription_starts_at' => now(),
                            'license_key' => $licenseKey,
                            'is_subscribed' => true,
                        ]);

                        // Call external API to add license
                        $this->addLicenseToExternalAPI($user, $licenseKey);
                    } else {
                        Log::info('License already assigned to user', ['user_id' => $user->id]);
                        $user->update([
                            'payment_gateway_id' => $paymentGatewayId,
                            'package_id' => $packageId,
                            'subscription_starts_at' => now(),
                            'is_subscribed' => true,
                        ]);
                    }

                    $order->status = 'completed';
                    $order->save();
                    Log::info('User', [
                        'user_payment_gateway_id' => $user->payment_gateway_id,
                        'user_package_id' => $user->package_id,
                        'user_subscription_starts_at' => $user->subscription_starts_at,
                        'user_is_subscribed' => $user->is_subscribed
                    ]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('FastSpring callback failed', ['message' => $e->getMessage()]);
                    return redirect()->route('subscriptions.index')
                        ->with('error', 'Something went wrong while processing your payment.');
                }

                return redirect()->route('user.dashboard')
                    ->with('success', 'Thank you for your payment! Your subscription is being processed and will be activated shortly.');
            } else {
                Log::warning('Invalid payment gateway specified', [
                    'gateway' => $gateway,
                    'request_data' => $request->all()
                ]);
                return redirect()->route('subscriptions.index')
                    ->with('error', 'Invalid payment gateway. Please try again or contact support.');
            }
        } catch (\Exception $e) {
            Log::error('Error processing payment success: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            return redirect()->route('subscriptions.index')
                ->with('error', 'An error occurred while processing your payment. Please try again or contact support.');
        }
    }

    private function verifyPayProGlobalPayment($orderId, $paymentId)
    {
        try {
            // Get API credentials from config
            $vendorAccountId = config('payment.gateways.PayProGlobal.merchant_id');
            $apiSecretKey = config('payment.gateways.PayProGlobal.api_secret');
            $apiUrl = config('payment.gateways.PayProGlobal.api_url', 'https://store.payproglobal.com/');

            if (empty($vendorAccountId) || empty($apiSecretKey)) {
                Log::error('PayProGlobal API credentials are not configured', [
                    'vendorAccountId' => $vendorAccountId,
                    'apiSecretKey' => $apiSecretKey ? 'Set' : 'Not Set'
                ]);
                return false;
            }

            Log::info('Attempting to verify PayProGlobal payment', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'api_url' => $apiUrl
            ]);

            // Create API client
            $client = new \GuzzleHttp\Client([
                'base_uri' => $apiUrl,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => false
            ]);

            // Get order details
            $response = $client->post('api/Orders/GetOrderDetails', [
                'json' => [
                    'vendorAccountId' => $vendorAccountId,
                    'apiSecretKey' => $apiSecretKey,
                    'orderId' => $orderId,
                    'dateFormat' => 'a'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $orderData = json_decode($responseBody, true);

            Log::info('PayProGlobal API response', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            if ($statusCode !== 200 || !isset($orderData['response']) || !$orderData['isSuccess']) {
                Log::error('PayProGlobal API returned an error or invalid response', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                return false;
            }

            $orderStatus = $orderData['response']['orderStatusName'] ?? null;
            $paymentStatus = $orderData['response']['paymentStatusName'] ?? null;

            Log::info('PayProGlobal order status', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus
            ]);

            // Check if order is paid and completed
            if ($orderStatus === 'Processed' && $paymentStatus === 'Paid') {
                Log::info('Payment verified successfully', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]);
                return true;
            }

            Log::warning('Payment not yet verified', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Error verifying PayProGlobal payment: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'exception' => $e
            ]);
            return false;
        }
    }

    public function handleFastSpringWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('FastSpring Webhook Received:', $payload);

        try {
            $secret = config('payment.gateways.FastSpring.webhook_secret');
            $signature = $request->header('X-Fs-Signature');

            // Verify webhook signature
            if ($secret && $signature) {
                $computedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
                if (!hash_equals($signature, $computedSignature)) {
                    Log::error('FastSpring Webhook: Invalid signature', [
                        'received' => $signature,
                        'computed' => $computedSignature,
                        'payload' => $payload
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            } elseif ($secret) {
                Log::warning('FastSpring Webhook: Missing X-Fs-Signature header', [
                    'headers' => $request->headers->all(),
                    'payload' => $payload
                ]);
                return response()->json(['error' => 'Missing signature header'], 400);
            }

            // Process the webhook event
            switch ($payload['type'] ?? null) {
                case 'order.completed':
                case 'subscription.activated':
                case 'subscription.charge.completed':
                    Log::info('Processing FastSpring webhook event', [
                        'event' => $payload['type'],
                        'order_id' => $payload['id'] ?? 'unknown'
                    ]);
                    return $this->handleFastSpringOrderCompleted($payload);
                default:
                    Log::debug('Ignoring FastSpring webhook event', [
                        'event' => $payload['type'] ?? 'unknown'
                    ]);
                    return response()->json(['status' => 'ignored']);
            }
        } catch (\Exception $e) {
            Log::error('Error processing FastSpring webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
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

    private function handleFastSpringOrderCompleted($payload)
    {
        try {
            $orderId = $payload['id'] ?? $payload['order'] ?? null;
            $userId = null;
            $package = null;
            $userEmail = $payload['contact']['email'] ?? null;
            $customerEmail = $payload['customer']['email'] ?? $userEmail;
            $total = $payload['total'] ?? 0;
            $currency = $payload['currency'] ?? 'USD';
            $status = $payload['status'] ?? 'completed';

            // Extract user ID and package from tags
            $tags = [];
            if (isset($payload['tags'])) {
                $tags = is_string($payload['tags']) ? json_decode($payload['tags'], true) : $payload['tags'];
                $userId = $tags['user_id'] ?? null;
                $package = $tags['package'] ?? null;
            }

            // Find user by email if no user ID
            if (!$userId && $customerEmail) {
                $user = User::where('email', $customerEmail)->first();
                if ($user) {
                    $userId = $user->id;
                }
            }

            if (!$userId) {
                Log::error('Could not determine user for FastSpring order', [
                    'order_id' => $orderId,
                    'email' => $customerEmail
                ]);
                return response()->json(['error' => 'User not found'], 404);
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error("User not found with ID: {$userId}");
                return response()->json(['error' => 'User not found'], 404);
            }

            $packageModel = Package::whereRaw('LOWER(name) = ?', [strtolower($package)])->first();
            if (!$packageModel) {
                Log::error("Package not found: {$package}");
                return response()->json(['error' => 'Package not found'], 404);
            }

            $order = Order::updateOrCreate(
                ['transaction_id' => $orderId],
                [
                    'user_id' => $userId,
                    'package_id' => $packageModel->id,
                    'amount' => $total,
                    'currency' => $currency,
                    'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                    'status' => $status === 'completed' ? 'completed' : 'pending',
                    'metadata' => array_merge($payload, ['tags' => $tags])
                ]
            );

            if ($order->status === 'completed') {
                // Assign license only if not already assigned
                if (!$user->license_key) {
                    $licenseKey = $this->makeLicense();
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                        'package_id' => $packageModel->id,
                        'subscription_starts_at' => now(),
                        'license_key' => $licenseKey,
                        'is_subscribed' => true,
                    ]);

                    // Call external API to add license
                    $this->addLicenseToExternalAPI($user, $licenseKey);
                } else {
                    Log::info('License already assigned to user', ['user_id' => $user->id]);
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('fastspring'),
                        'package_id' => $packageModel->id,
                        'subscription_starts_at' => now(),
                        'is_subscribed' => true,
                    ]);
                }

                Log::info('FastSpring subscription activated', [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'package' => $package,
                    'license_key' => $user->license_key,
                ]);
            }

            return response()->json([
                'status' => 'processed',
                'order_id' => $orderId,
                'user_id' => $userId,
                'package' => $package
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process FastSpring order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
            return response()->json([
                'error' => 'Processing failed'
            ], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        Log::debug('PayProGlobal Webhook Received', ['payload' => $request->all()]);

        try {
            $secretKey = Config::get('payment.gateways.PayProGlobal.webhook_secret');
            $signature = $request->header('X-PayPro-Signature');

            if ($secretKey && !$signature) {
                Log::error('Missing X-PayPro-Signature header');
                return response()->json(['error' => 'Missing signature header'], 400);
            }

            if ($secretKey) {
                $expectedSignature = hash_hmac('sha256', $request->getContent(), $secretKey);
                if (!hash_equals($signature, $expectedSignature)) {
                    Log::error('Invalid signature', [
                        'received' => $signature,
                        'expected' => $expectedSignature
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }

            $payload = $request->all();
            $orderStatus = $payload['order_status'] ?? null;
            $paymentStatus = $payload['payment_status'] ?? null;
            $isSuccessful = ($orderStatus === 'Processed' || $paymentStatus === 'Paid');

            if (!$isSuccessful) {
                Log::info('Ignoring non-successful webhook', [
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus
                ]);
                return response()->json(['status' => 'ignored']);
            }

            $customData = isset($payload['custom']) ? json_decode($payload['custom'], true) : [];
            $userId = $customData['user_id'] ?? null;
            $packageName = $customData['package'] ?? null;
            $orderId = $payload['order_id'] ?? null;

            if (!$userId || !$packageName || !$orderId) {
                Log::error('Missing required data', [
                    'user_id' => $userId,
                    'package' => $packageName,
                    'order_id' => $orderId
                ]);
                return response()->json(['error' => 'Missing required data'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error("User not found: {$userId}");
                return response()->json(['error' => 'User not found'], 404);
            }

            $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
            if (!$package) {
                Log::error("Package not found: {$packageName}");
                return response()->json(['error' => 'Package not found'], 404);
            }

            DB::beginTransaction();
            try {
                $order = Order::updateOrCreate(
                    ['transaction_id' => $orderId],
                    [
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'amount' => $package->price,
                        'currency' => 'USD',
                        'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                        'status' => 'completed',
                    ]
                );

                // Assign license only if not already assigned
                if (!$user->license_key) {
                    $licenseKey = $this->makeLicense();
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                        'package_id' => $package->id,
                        'subscription_starts_at' => now(),
                        'license_key' => $licenseKey,
                        'is_subscribed' => true,
                    ]);

                    // Call external API to add license
                    $this->addLicenseToExternalAPI($user, $licenseKey);
                } else {
                    Log::info('License already assigned to user', ['user_id' => $user->id]);
                    $user->update([
                        'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                        'package_id' => $package->id,
                        'subscription_starts_at' => now(),
                        'is_subscribed' => true,
                    ]);
                }

                DB::commit();

                Log::info('PayProGlobal payment processed', [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'package' => $packageName,
                    'license_key' => $user->license_key,
                ]);

                return response()->json(['status' => 'processed']);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Webhook processing failed', ['message' => $e->getMessage()]);
                return response()->json(['error' => 'Processing failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error processing webhook', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function verifyPaddlePayment(Request $request)
    {
        $transactionId = $request->query('transaction_id');

        Log::info('Verifying Paddle payment', ['transaction_id' => $transactionId]);

        if (!$transactionId) {
            Log::error('Missing transaction_id in Paddle verification request');
            return redirect()->route('subscriptions.index')
                ->with('error', 'Invalid payment verification request.');
        }

        try {
            // Check if order already processed
            $order = Order::where('transaction_id', $transactionId)->first();
            if ($order && $order->status === 'completed') {
                Log::info('Paddle payment already verified', ['transaction_id' => $transactionId]);
                return redirect()->route('user.dashboard')
                    ->with('success', 'Thank you for your payment! Your subscription is now active.');
            }

            // Verify with Paddle API
            $apiKey = config('payment.gateways.Paddle.api_key');
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ];

            $response = Http::withHeaders($headers)
                ->get("https://sandbox-api.paddle.com/transactions/{$transactionId}");

            if (!$response->successful()) {
                Log::error('Paddle API transaction fetch failed', [
                    'transaction_id' => $transactionId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return redirect()->route('subscriptions.index')
                    ->with('error', 'Payment verification failed. Please contact support.');
            }

            $transactionData = $response->json()['data'];

            if ($transactionData['status'] !== 'completed' && $transactionData['status'] !== 'paid') {
                Log::warning('Paddle transaction not completed', [
                    'transaction_id' => $transactionId,
                    'status' => $transactionData['status']
                ]);
                return redirect()->route('subscriptions.index')
                    ->with('error', 'Payment is not yet completed. Please try again later.');
            }

            // Process payment if not already processed
            $this->processPaddlePaymentFromTransaction($transactionData);

            return redirect()->route('user.dashboard')
                ->with('success', 'Thank you for your payment! Your subscription is now active.');
        } catch (\Exception $e) {
            Log::error('Paddle payment verification error', [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('subscriptions.index')
                ->with('error', 'An error occurred while verifying your payment. Please contact support.');
        }
    }

    private function processPaddlePaymentFromTransaction($transactionData)
    {
        DB::beginTransaction();
        try {
            $userId = $transactionData['custom_data']['user_id'] ?? null;
            $packageId = $transactionData['custom_data']['package_id'] ?? null;
            $transactionId = $transactionData['id'];

            if (!$userId || !$packageId) {
                throw new \Exception('Missing user or package information');
            }

            $user = User::find($userId);
            $package = Package::find($packageId);

            if (!$user || !$package) {
                throw new \Exception('User or package not found');
            }

            // Update or create order
            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount' => ($transactionData['details']['totals']['total'] ?? 0) / 100,
                    'currency' => $transactionData['currency_code'],
                    'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                    'status' => 'completed',
                    'metadata' => $transactionData
                ]
            );

            // Update user subscription
            $user->update([
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'package_id' => $package->id,
                'subscription_starts_at' => now(),
                'license_key' => $this->makeLicense() ?? null,
            ]);

            DB::commit();

            Log::info('Paddle payment processed successfully', [
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'package_id' => $package->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process Paddle payment', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function verifyPayProGlobalPaymentStatus(Request $request, $paymentId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Use the existing verifyPayProGlobalPayment method
            $isVerified = $this->verifyPayProGlobalPayment($paymentId, $paymentId);

            return response()->json([
                'status' => $isVerified ? 'completed' : 'pending',
                'message' => $isVerified ? 'Payment verified successfully' : 'Payment not yet verified'
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying PayProGlobal payment status: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'exception' => $e
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify payment status'
            ], 500);
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
            'subscription_starts_at' => now(),
            'license_key' => $this->makeLicense() ?? null,
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
                $apiSecretKey = config('payment.gateways.PayProGlobal.api_secret');

                if (empty($vendorAccountId) || empty($apiSecretKey)) {
                    Log::error('PayProGlobal API credentials not configured for orders list');
                    return view('subscription.order', compact('paymentGateway'))->with('error', 'Payment system is not properly configured');
                }

                // Batch fetch orders with pagination support
                $cacheKey = "paypro_orders_{$user->id}";
                $ordersData = Cache::remember($cacheKey, 300, function () use ($client, $vendorAccountId, $apiSecretKey) {
                    $response = $client->post('https://store.payproglobal.com/api/Orders/GetList', [
                        'json' => [
                            'vendorAccountId' => $vendorAccountId,
                            'apiSecretKey' => $apiSecretKey,
                            'dateFormat' => 'a',
                            'pageSize' => 50,
                        ],
                        'headers' => [
                            'accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ],
                    ]);
                    return json_decode($response->getBody(), true);
                });

                $orderIds = array_column($ordersData['response'] ?? [], 'orderId');

                // Batch fetch order details
                foreach (array_chunk($orderIds, 10) as $chunk) {
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
                $apiKey = config('payment.gateways.Paddle.api_key');

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
    public function handlePopupCancel()
    {
        return view('payment.popup-cancel');
    }

    private function makeLicense($user = null)
    {
        return Str::uuid()->toString(); // Generates a UUID as license key
    }

    private function addLicenseToExternalAPI($user, $licenseKey)
    {
        try {
            $tenantId = $user->tenant_id;
            if (!$tenantId) {
                Log::error('Tenant ID not found for user', ['user_id' => $user->id]);
                return false;
            }

            $response = Http::post(config('payment.gateways.License API.endpoint'), [
                'tenantId' => $tenantId,
                'subscriptionCode' => config('payment.gateways.License API.subscription_code'),
            ]);

            if ($response->successful() && $response->json()['code'] === 200) {
                Log::info('License added successfully via API', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'license_key' => $licenseKey,
                ]);
                return true;
            } else {
                Log::error('Failed to add license via API', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error calling license API', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
