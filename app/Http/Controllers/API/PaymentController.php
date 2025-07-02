<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\{Package, User, PaymentGateways, Order};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
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
            return response()->json(['error' => 'Invalid package selected'], 400);
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

    private function makeLicense($user = null)
    {
        $cacheKey = 'license_summary_' . ($user ? $user->id : 'general');
        $summaryData = Cache::remember($cacheKey, 300, function () {
            $summary = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/channel/inventory/subscription/summary/search', [
                'pageIndex' => 1,
                'pageSize' => 100,
                'appIds' => [1],
                'subscriptionType' => 'license',
            ]);

            if (!$summary->successful() || $summary->json()['code'] !== 200) {
                Log::error('Failed to fetch subscription summary in makeLicense', [
                    'response' => $summary->body(),
                ]);
                return null;
            }

            return $summary->json()['data']['data'] ?? [];
        });

        if (empty($summaryData)) {
            Log::error('No subscription data found in cached summary response');
            return null;
        }

        $licenseKey = $summaryData[0]['subscriptionCode'] ?? null;
        if (!$licenseKey) {
            Log::error('Subscription code not found in cached summary response');
            return null;
        }

        Log::info('License key retrieved from cache or API', ['license_key' => $licenseKey]);
        return $licenseKey;
    }

    private function addLicenseToExternalAPI($user, $licenseKey)
    {
        try {
            $tenantId = $user->tenant_id;
            if (!$tenantId) {
                Log::error('Tenant ID not found for user', ['user_id' => $user->id]);
                return false;
            }

            $payload = [
                'tenantId' => $tenantId,
                'subscriptionCode' => $licenseKey,
            ];

            $response = Http::post(config('payment.gateways.License API.endpoint'), $payload);

            if ($response->successful() && $response->json()['code'] === 200) {
                $user->update([
                    'license_key' => $licenseKey
                ]);

                Log::info('License added successfully via API', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'license_key' => $licenseKey
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

    public function paddleCheckout(Request $request, string $package)
    {
        Log::info('Paddle checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            $apiKey = config('payment.gateways.Paddle.api_key');
            if (empty($apiKey)) {
                Log::error('Paddle API key missing');
                return response()->json(['error' => 'Payment configuration error'], 500);
            }

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];

            if (!$user->paddle_customer_id) {
                $customerResponse = Http::withHeaders($headers)->post('https://sandbox-api.paddle.com/customers', [
                    'email' => $user->email,
                    'name' => $user->name,
                    'custom_data' => ['user_id' => (string) $user->id]
                ]);

                if (!$customerResponse->successful()) {
                    Log::error('Paddle customer creation failed', ['user_id' => $user->id]);
                    return response()->json(['error' => 'Customer setup failed'], 500);
                }

                $user->paddle_customer_id = $customerResponse->json()['data']['id'];
                $user->save();
            }

            $productsResponse = Http::withHeaders($headers)
                ->get('https://sandbox-api.paddle.com/products', ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Paddle products fetch failed', ['status' => $productsResponse->status()]);
                return response()->json(['error' => 'Product fetch failed'], 500);
            }

            $products = $productsResponse->json()['data'];
            $matchingProduct = collect($products)->firstWhere('name', $processedPackage);

            if (!$matchingProduct) {
                Log::error('Paddle product not found', ['package' => $processedPackage]);
                return response()->json(['error' => 'Unavailable package'], 400);
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
                    'package' => $processedPackage
                ],
                'checkout' => [
                    'settings' => ['display_mode' => 'overlay'],
                    'success_url' => route('payments.success', ['gateway' => 'paddle', 'transaction_id' => '{transaction_id}', 'popup' => 'true'])
                ]
            ];

            $response = Http::withHeaders($headers)
                ->post('https://sandbox-api.paddle.com/transactions', $transactionData);

            if (!$response->successful()) {
                Log::error('Paddle transaction creation failed', ['status' => $response->status(), 'response' => $response->body()]);
                return response()->json(['error' => 'Transaction creation failed'], $response->status());
            }

            $transaction = $response->json()['data'];

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $transaction['details']['totals']['total'] / 100,
                'currency' => $transaction['currency_code'],
                'transaction_id' => $transaction['id'],
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'status' => 'pending',
                'metadata' => ['checkout_url' => $transaction['checkout']['url']]
            ]);

            Log::info('Paddle checkout created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction['id'],
                'checkout_url' => $transaction['checkout']['url']
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $transaction['checkout']['url'],
                'transaction_id' => $transaction['id']
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function fastspringCheckout(Request $request, $package)
    {
        Log::info('FastSpring checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];
            $isUpgrade = $request->input('is_upgrade', false);

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
                'amount' => $packageData->price,
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
        Log::info('PayProGlobal checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = str_replace('-plan', '', strtolower($package));
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            $productId = config("payment.gateways.PayProGlobal.product_ids.{$processedPackage}");
            if (!$productId) {
                Log::error('PayProGlobal product ID not found', ['package' => $processedPackage]);
                return response()->json(['error' => 'Product not configured'], 400);
            }

            $secretKey = config('payment.gateways.PayProGlobal.webhook_secret');
            $testMode = config('payment.gateways.PayProGlobal.test_mode', true);

            $successParams = [
                'gateway' => 'payproglobal',
                'order_id' => '{order_id}',
                'user_id' => $user->id,
                'package' => $processedPackage,
                'popup' => 'true'
            ];

            $checkoutUrl = "https://store.payproglobal.com/checkout?" . http_build_query([
                'products[1][id]' => $productId,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'custom' => json_encode([
                    'user_id' => $user->id,
                    'package_id' => $packageData->id,
                    'package' => $processedPackage
                ]),
                'page-template' => 'ID',
                'currency' => 'USD',
                'use-test-mode' => $testMode ? 'true' : 'false',
                'secret-key' => $secretKey,
                'success-url' => route('payments.success', $successParams),
                'cancel-url' => route('payments.popup-cancel')
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->price,
                'currency' => 'USD',
                'transaction_id' => 'PPG-PENDING-' . Str::random(10),
                'payment_gateway_id' => $this->getPaymentGatewayId('payproglobal'),
                'status' => 'pending',
                'metadata' => ['checkout_url' => $checkoutUrl]
            ]);

            Log::info('PayProGlobal checkout created', [
                'user_id' => $user->id,
                'transaction_id' => $order->transaction_id,
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl
            ]);
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Checkout failed', 'message' => $e->getMessage()], 500);
        }
    }

    private function getSubscriptionId($orderId)
    {
        // Fetch order details
        $response = Http::withBasicAuth(
            config('payment.gateways.FastSpring.username'),
            config('payment.gateways.FastSpring.password')
        )->get("https://api.fastspring.com/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('FastSpring order verification failed', [
                'order_id' => $orderId,
                'response' => $response->body(),
            ]);
            return ['error' => 'Order verification failed.']; // Return array instead of JsonResponse
        }

        $order = $response->json();
        $subscriptionId = $order['items'][0]['subscription'] ?? null; // Fixed typo: $validated → $order

        if (!$subscriptionId) {
            return ['error' => 'No subscription found for this order.']; // Error array
        }

        // Fetch subscription details
        $subscriptionResponse = Http::withBasicAuth(
            config('payment.gateways.FastSpring.username'),
            config('payment.gateways.FastSpring.password')
        )->get("https://api.fastspring.com/subscriptions/{$subscriptionId}");

        if ($subscriptionResponse->failed()) {
            Log::error('FastSpring subscription verification failed', [
                'subscription_id' => $subscriptionId,
                'response' => $subscriptionResponse->body(),
            ]);
            return ['error' => 'Subscription verification failed.']; // Error array
        }

        return $subscriptionResponse->json(); // Success: subscription array
    }

    public function handleSuccess(Request $request)
    {
        Log::info('Payment success callback', [
            'params' => $request->all(),
            'query' => $request->query(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        try {
            $gateway = $request->input('gateway', $request->query('gateway'));
            if (empty($gateway)) {
                Log::error('No gateway specified in success callback', [
                    'params' => $request->all(),
                    'query' => $request->query(),
                    'url' => $request->fullUrl()
                ]);
                return redirect()->route('pricing')->with('error', 'Invalid payment gateway');
            }

            if ($request->query('popup') === 'true' || $request->input('popup') === 'true') {
                Log::info('Popup success view returned', ['gateway' => $gateway]);
                return view('payment.popup-success');
            }

            if ($gateway === 'paddle') {
                $transactionId = $request->query('transaction_id') ?? $request->input('transaction_id');
                if (!$transactionId) {
                    Log::error('Missing Paddle transaction_id', ['params' => $request->all()]);
                    return redirect()->route('pricing')->with('error', 'Invalid payment request');
                }

                $order = Order::where('transaction_id', $transactionId)->first();
                if ($order && $order->status === 'completed') {
                    Log::info('Order already completed', ['transaction_id' => $transactionId]);
                    return redirect()->route('user.dashboard')->with('success', 'Subscription active');
                }

                $apiKey = config('payment.gateways.Paddle.api_key');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])->get("https://sandbox-api.paddle.com/transactions/{$transactionId}");

                if (!$response->successful() || !in_array($response->json()['data']['status'], ['completed', 'paid'])) {
                    Log::error('Paddle transaction verification failed', [
                        'transaction_id' => $transactionId,
                        'response' => $response->body()
                    ]);
                    return redirect()->route('pricing')->with('error', 'Payment verification failed');
                }

                $transactionData = $response->json()['data'];
                return $this->processPayment($transactionData, 'paddle');
            }

            if ($gateway === 'fastspring') {
                $orderId = $request->input('orderId') ?? $request->query('orderId');
                $packageName = $request->input('package_name') ?? $request->query('package_name');

                $subscriptionData = $this->getSubscriptionId($orderId); // Returns the array
                // Handle errors from getSubscriptionId
                if (isset($subscriptionData['error'])) {
                    Log::error('FastSpring Error: ' . $subscriptionData['error']);
                    return redirect()->route('pricing')->with('error', $subscriptionData['error']);
                }

                // Proceed if successfull
                $subscriptionId = $subscriptionData['id'];

                if (!$orderId) {
                    Log::error('Missing FastSpring orderId', ['params' => $request->all()]);
                    return redirect()->route('pricing')->with('error', 'Invalid order ID');
                }

                $order = Order::where('transaction_id', $orderId)->first();
                if ($order && $order->status === 'completed') {
                    Log::info('Order already completed', ['order_id' => $orderId]);
                    return redirect()->route('user.dashboard')->with('success', 'Subscription active');
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
                    return redirect()->route('pricing')->with('error', 'Order verification failed');
                }

                $orderData = $response->json()['orders'][0] ?? $response->json();

                return $this->processPayment(array_merge($orderData, [
                    'order_id' => $orderId,
                    'package' => $packageName,
                    'subscription_id' => $subscriptionId
                ]), 'fastspring');
            }

            if ($gateway === 'payproglobal') {
                $orderId = $request->query('order_id') ?? $request->input('order_id');
                $userId = $request->query('user_id') ?? $request->input('user_id');
                $packageName = $request->query('package') ?? $request->input('package');

                if (!$orderId || !$userId || !$packageName) {
                    Log::error('Missing PayProGlobal parameters', [
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'package' => $packageName,
                        'params' => $request->all()
                    ]);
                    return redirect()->route('pricing')->with('error', 'Invalid payment parameters');
                }

                $isVerified = $this->verifyPayProGlobalPayment($orderId, $orderId);
                if (!$isVerified) {
                    Log::error('PayProGlobal payment verification failed', ['order_id' => $orderId]);
                    return redirect()->route('pricing')->with('error', 'Payment verification failed');
                }

                return $this->processPayment([
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'package' => $packageName,
                    'amount' => 0,
                    'currency' => 'USD'
                ], 'payproglobal');
            }

            Log::error('Invalid gateway in success callback', ['gateway' => $gateway]);
            return redirect()->route('pricing')->with('error', 'Invalid gateway');
        } catch (\Exception $e) {
            Log::error('Payment success processing failed', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'url' => $request->fullUrl()
            ]);
            return redirect()->route('pricing')->with('error', 'Payment processing failed');
        }
    }

    private function processPayment($paymentData, $gateway)
    {
        return DB::transaction(function () use ($paymentData, $gateway) {
            $userId = Auth::user()->id ?? null;
            $packageName = ucfirst($paymentData['package']) ?? (ucfirst($paymentData['custom_data']['package']) ?? null);
            $transactionId = $paymentData['order'] ?? ($paymentData['id'] ?? null);
            $amount = $paymentData['total'] ?? ($paymentData['items'][0]['subtotal'] / 100 ?? 0);
            $subscriptionId = $paymentData['subscription_id'] ?? null;
            $action = $paymentData['action'] ?? ($paymentData['custom_data']['action'] ?? 'new');
            $currency = $paymentData['currency'] ?? 'USD';

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

            $orderData = [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                'status' => 'completed',
                'metadata' => $paymentData
            ];

            $order = Order::updateOrCreate(
                ['transaction_id' => $transactionId],
                $orderData
            );

            if (!$user->license_key || $action === 'upgrade') {
                $licenseKey = $this->makeLicense($user);
                if (!$licenseKey) {
                    Log::error('Failed to generate license key', ['user_id' => $user->id]);
                    throw new \Exception('License generation failed');
                }
                $user->update([
                    'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                    'package_id' => $package->id,
                    'subscription_starts_at' => now(),
                    'license_key' => $licenseKey,
                    'is_subscribed' => true,
                    'subscription_id' => $subscriptionId
                ]);
                $this->addLicenseToExternalAPI($user, $licenseKey, $subscriptionId);
            } else {
                $user->update([
                    'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                    'package_id' => $package->id,
                    'subscription_starts_at' => now(),
                    'is_subscribed' => true,
                    'subscription_id' => $subscriptionId
                ]);
            }

            Log::info('Payment processed successfully', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'subscription_id' => $subscriptionId,
                'action' => $action
            ]);

            return redirect()->route('user.dashboard')->with('success', $action === 'upgrade' ? 'Subscription upgraded' : 'Subscription activated');
        });
    }

    private function verifyPayProGlobalPayment($orderId, $paymentId)
    {
        try {
            $vendorAccountId = config('payment.gateways.PayProGlobal.merchant_id');
            $apiSecretKey = config('payment.gateways.PayProGlobal.api_key');

            $response = Http::post('https://store.payproglobal.com/api/Orders/GetOrderDetails', [
                'vendorAccountId' => $vendorAccountId,
                'apiSecretKey' => $apiSecretKey,
                'orderId' => $orderId,
                'dateFormat' => 'a'
            ]);

            $orderData = $response->json();
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
        try {
            $payload = $request->all();
            Log::info('Paddle webhook received', ['payload' => $payload]);

            if ($payload['event_type'] === 'transaction.completed' || $payload['event_type'] === 'transaction.paid') {
                return $this->processPayment($payload['data'], 'paddle');
            }
            Log::info('Paddle webhook ignored', ['event_type' => $payload['event_type']]);
            return response()->json(['status' => 'ignored']);
        } catch (\Exception $e) {
            Log::error('Paddle webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handleFastSpringWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('FastSpring webhook received', ['payload' => $payload]);

            if (in_array($payload['type'] ?? null, ['order.completed', 'subscription.activated', 'subscription.charge.completed', 'subscription.updated'])) {
                $tags = is_string($payload['tags'] ?? '') ? json_decode($payload['tags'], true) : ($payload['tags'] ?? []);
                $paymentData = array_merge($payload, [
                    'user_id' => $tags['user_id'] ?? null,
                    'package' => $tags['package'] ?? null,
                    'subscription_id' => $payload['subscription'] ?? null,
                    'action' => $tags['action'] ?? 'new'
                ]);
                return $this->processPayment($paymentData, 'fastspring');
            }
            Log::info('FastSpring webhook ignored', ['type' => $payload['type']]);
            return response()->json(['status' => 'ignored']);
        } catch (\Exception $e) {
            Log::error('FastSpring webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function handlePayProGlobalWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('PayProGlobal webhook received', ['payload' => $payload]);

            if (($payload['order_status'] ?? null) === 'Processed' && ($payload['payment_status'] ?? null) === 'Paid') {
                $customData = json_decode($payload['custom'] ?? '{}', true);
                return $this->processPayment([
                    'order_id' => $payload['order_id'],
                    'user_id' => $customData['user_id'] ?? null,
                    'package' => $customData['package'] ?? null,
                    'amount' => 0,
                    'currency' => 'USD',
                    'action' => $customData['action'] ?? 'new'
                ], 'payproglobal');
            }
            Log::info('PayProGlobal webhook ignored', [
                'order_status' => $payload['order_status'],
                'payment_status' => $payload['payment_status']
            ]);
            return response()->json(['status' => 'ignored']);
        } catch (\Exception $e) {
            Log::error('PayProGlobal webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function getOrdersList(Request $request)
    {
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
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
        Log::info('Subscription cancellation started', ['user_id' => Auth::id()]);

        try {
            $user = Auth::user();
            if (!$user->is_subscribed || !$user->subscription_id) {
                Log::error('User has no active subscription to cancel', ['user_id' => $user->id]);
                return redirect()->back()->with('error', 'No active subscription to cancel');
            }

            $gateway = $user->paymentGateway ? $user->paymentGateway->name : null;
            if (!$gateway) {
                Log::error('No payment gateway associated with user', ['user_id' => $user->id]);
                return redirect()->back()->with('error', 'No payment gateway found');
            }

            $gateway = strtolower($gateway);
            $subscriptionId = $user->subscription_id;

            if ($gateway === 'fastspring') {
                $response = Http::withBasicAuth(
                    config('payment.gateways.FastSpring.username'),
                    config('payment.gateways.FastSpring.password')
                )->delete("https://api.fastspring.com/subscriptions/{$subscriptionId}");

                // Check HTTP status first
                if (!$response->successful()) {
                    Log::error('FastSpring subscription cancellation failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'response' => $response->body(),
                        'status' => $response->status()
                    ]);
                    return redirect()->back()->with('error', 'Failed to cancel subscription');
                }

                $responseData = $response->json();

                // Validate response structure and success indicators
                // if (!isset($responseData['subscriptions'][0]['action']) ||
                //     !isset($responseData['subscriptions'][0]['result']) ||
                //     $responseData['subscriptions'][0]['action'] !== 'subscription.cancel' ||
                //     $responseData['subscriptions'][0]['result'] !== 'success') {
                //     Log::error('FastSpring subscription cancellation invalid response', [
                //         'user_id' => $user->id,
                //         'subscription_id' => $subscriptionId,
                //         'response' => $responseData
                //     ]);
                //     return redirect()->back()->with('error', 'Subscription cancellation did not confirm');
                // }

                // Update user subscription status
                $user->update([
                    'is_subscribed' => 0,
                    'subscription_id' => null,
                    'subscription_ends_at' => null,
                    'subscription_starts_at' => null,
                    'payment_gateway_id' => null,
                    'package_id' => null,
                ]);

                // CORRECTED: Proper way to update the order status
                Order::where('user_id', $user->id)
                    ->where('subscription_id', $subscriptionId)
                    ->update(['status' => 'canceled']);

                Log::info('FastSpring subscription canceled', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId
                ]);
            } elseif ($gateway === 'paddle') {
                $apiKey = config('payment.gateways.Paddle.api_key');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])->delete("https://sandbox-api.paddle.com/subscriptions/{$subscriptionId}");

                if (!$response->successful() || $response->json()['data']['status'] !== 'canceled') {
                    Log::error('Paddle subscription cancellation failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'response' => $response->body()
                    ]);
                    return redirect()->back()->with('error', 'Failed to cancel subscription');
                }

                Log::info('Paddle subscription canceled', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId
                ]);
            } elseif ($gateway === 'payproglobal') {
                // PayProGlobal cancellation requires contacting support or custom API integration
                Log::warning('PayProGlobal cancellation not fully supported', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId
                ]);
                return redirect()->back()->with('error', 'Please contact support to cancel PayProGlobal subscription');
            } else {
                Log::error('Unsupported payment gateway for cancellation', ['gateway' => $gateway, 'user_id' => $user->id]);
                return redirect()->back()->with('error', 'Unsupported payment gateway');
            }

            // Update user and order records
            DB::transaction(function () use ($user, $subscriptionId) {
                $user->update([
                    'is_subscribed' => 0,
                    'subscription_id' => null,
                    'subscription_ends_at' => now(),
                    'subscription_starts_at' => null,
                    'package_id' => null,
                    'payment_gateway_id' => null
                ]);

                Order::where('user_id', $user->id)
                    ->where('subscription_id', $subscriptionId)
                    ->update(['status' => 'canceled']);
            });

            Log::info('Subscription cancellation completed', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription canceled successfully'
                ]);
            }

            return redirect()->back()->with('success', 'Subscription canceled successfully');
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

            return redirect()->back()->with('error', 'Cancellation failed');
        }
    }
}
