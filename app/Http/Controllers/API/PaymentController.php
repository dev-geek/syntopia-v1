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

    private function processPackageName($package)
    {
        return str_replace('-plan', '', strtolower($package));
    }

    private function checkLicenseAvailability()
    {
        $cacheKey = 'license_availability_check';
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
                Log::error('Failed to fetch subscription summary in checkLicenseAvailability', [
                    'response' => $summary->body(),
                ]);
                return null;
            }

            return $summary->json()['data']['data'] ?? [];
        });

        if (empty($summaryData)) {
            Log::error('No subscription data found in license availability check');
            return false;
        }

        $licenseKey = $summaryData[0]['subscriptionCode'] ?? null;
        if (!$licenseKey) {
            Log::error('No license codes available in license availability check');
            return false;
        }

        Log::info('License availability check passed', ['available' => true]);
        return true;
    }

    private function makeLicense($user = null, $bypassCache = false)
    {
        if ($bypassCache) {
            // Make fresh API call without using cache
            Log::info('Making fresh license API call (bypassing cache)', [
                'user_id' => $user ? $user->id : null
            ]);

            $summary = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/subscription/search', [
                'pageIndex' => 1,
                'pageSize' => 100,
                'appIds' => [1],
                'subscriptionType' => 'license',
            ]);

            if (!$summary->successful() || $summary->json()['code'] !== 200) {
                Log::error('Failed to fetch subscription summary in makeLicense (fresh call)', [
                    'response' => $summary->body(),
                ]);
                return null;
            }

            $summaryData = $summary->json()['data']['data'] ?? [];
        } else {
            // Use cache for other scenarios
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
        }

        if (empty($summaryData)) {
            Log::error('No subscription data found in summary response', [
                'bypass_cache' => $bypassCache
            ]);
            return null;
        }

        $licenseKey = $summaryData[0]['subscriptionCode'] ?? null;
        if (!$licenseKey) {
            Log::error('Subscription code not found in summary response', [
                'bypass_cache' => $bypassCache
            ]);
            return null;
        }

        Log::info('License key retrieved', [
            'license_key' => $licenseKey,
            'bypass_cache' => $bypassCache
        ]);
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

            $response = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/subscription/license/add', $payload);

            $responseData = $response->json();
            Log::info("License response", $responseData);

            if ($response->successful() && $responseData['code'] === 200) {
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
                // Handle specific error codes
                $errorCode = $responseData['code'] ?? null;
                $errorMessage = $responseData['message'] ?? 'Unknown error';

                if ($errorCode === 710) {
                    Log::error('License API resource insufficiency', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'trace_id' => $responseData['traceId'] ?? null
                    ]);
                    // For resource insufficiency, we might want to retry later or notify admin
                    return false;
                }

                Log::error('Failed to add license via API', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
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
        \Log::info('[paddleCheckout] called', ['package' => $package, 'user_id' => \Auth::id()]);
        Log::info('Paddle checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        // Log Paddle configuration for debugging
        Log::info('Paddle configuration', [
            'api_key_exists' => !empty(config('payment.gateways.Paddle.api_key')),
            'environment' => config('payment.gateways.Paddle.environment', 'sandbox'),
            'api_url' => config('payment.gateways.Paddle.api_url')
        ]);

        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];
            $isUpgrade = $request->input('is_upgrade', false);
            $isDowngrade = $request->input('is_downgrade', false);

            // For paid packages, check license availability from API and update user's license
            if (!$packageData->isFree()) {
                Log::info('Checking license availability from API for paid package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'package_price' => $packageData->price,
                    'is_upgrade' => $isUpgrade,
                    'is_downgrade' => $isDowngrade
                ]);

                // Get license from API (bypass cache for fresh license)
                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('License not available from API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Try to add license to external API first
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name,
                        'license_key' => $licenseKey
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Only update user's license in database after successful external API call
                $user->update(['license_key' => $licenseKey]);

                Log::info('License successfully added to external API and updated in user table', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'license_key' => $licenseKey
                ]);
            } else {
                Log::info('Skipping license check for free package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
            }

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
                        $user->paddle_customer_id = $existingCustomer['id'];
                        $user->save();

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
                    if ($customerResponse->status() === 409 &&
                        isset($responseData['error']['code']) &&
                        $responseData['error']['code'] === 'customer_already_exists') {

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
                            $user->paddle_customer_id = $customerId;
                            $user->save();

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
                        Log::error('Paddle customer creation failed', [
                            'user_id' => $user->id,
                            'status' => $customerResponse->status(),
                            'response' => $customerResponse->body(),
                            'request_data' => $customerData
                        ]);
                        return response()->json([
                            'error' => 'Customer setup failed',
                            'details' => $customerResponse->body()
                        ], 500);
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

                    $user->paddle_customer_id = $customerData['data']['id'];
                    $user->save();

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
                    'package' => $package
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
        \Log::info('[fastspringCheckout] called', ['package' => $package, 'user_id' => \Auth::id()]);
        Log::info('FastSpring checkout started', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];
            $isUpgrade = $request->input('is_upgrade', false);
            $isDowngrade = $request->input('is_downgrade', false);

            // For paid packages, check license availability from API and update user's license
            if (!$packageData->isFree()) {
                Log::info('Checking license availability from API for paid package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'package_price' => $packageData->price,
                    'is_upgrade' => $isUpgrade,
                    'is_downgrade' => $isDowngrade
                ]);

                // Get license from API (bypass cache for fresh license)
                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('License not available from API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Try to add license to external API first
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name,
                        'license_key' => $licenseKey
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Only update user's license in database after successful external API call
                $user->update(['license_key' => $licenseKey]);

                Log::info('License successfully added to external API and updated in user table', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'license_key' => $licenseKey
                ]);
            } else {
                Log::info('Skipping license check for free package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
            }

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
        Log::info('PayProGlobal checkout started', [
            'package' => $package,
            'user_id' => Auth::id(),
            'is_upgrade' => $request->input('is_upgrade', false),
            'is_downgrade' => $request->input('is_downgrade', false)
        ]);

        try {
            $processedPackage = $this->processPackageName($package);
            $user = Auth::user();

            if (!$user) {
                Log::error('User not authenticated for PayProGlobal checkout');
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $packageData = Package::whereRaw('LOWER(name) = ?', [$processedPackage])->first();
            if (!$packageData) {
                Log::error('Package not found', ['package' => $processedPackage]);
                return response()->json(['error' => 'Invalid package selected'], 400);
            }

            // For paid packages, check license availability from API and update user's license
            if (!$packageData->isFree()) {
                Log::info('Checking license availability from API for paid package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'package_price' => $packageData->price,
                    'is_upgrade' => $request->input('is_upgrade', false),
                    'is_downgrade' => $request->input('is_downgrade', false)
                ]);

                // Get license from API (bypass cache for fresh license)
                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('License not available from API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Try to add license to external API first
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API - blocking checkout', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name,
                        'license_key' => $licenseKey
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Only update user's license in database after successful external API call
                $user->update(['license_key' => $licenseKey]);

                Log::info('License successfully added to external API and updated in user table', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'license_key' => $licenseKey
                ]);
            } else {
                Log::info('Skipping license check for free package', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
            }

            $productId = config("payment.gateways.PayProGlobal.product_ids.{$processedPackage}");
            if (!$productId) {
                Log::error('PayProGlobal product ID not configured', ['package' => $processedPackage]);
                return response()->json(['error' => 'Product not configured'], 400);
            }

            // Create a pending order
            $pendingOrderId = 'PPG-PENDING-' . Str::random(10);
            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'amount' => $packageData->price,
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

            // Build success URL with all necessary parameters
            $successParams = [
                'gateway' => 'payproglobal',
                'user_id' => $user->id,
                'package' => $processedPackage,
                'popup' => 'true',
                'pending_order_id' => $pendingOrderId,
                'action' => $request->input('is_upgrade') ? 'upgrade' : ($request->input('is_downgrade') ? 'downgrade' : 'new')
            ];

            $successUrl = route('payments.success', $successParams);

            // Build checkout URL
            $checkoutParams = [
                'products[1][id]' => $productId,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'custom' => json_encode([
                    'user_id' => $user->id,
                    'package_id' => $packageData->id,
                    'package' => $processedPackage,
                    'pending_order_id' => $pendingOrderId,
                    'action' => $request->input('is_upgrade') ? 'upgrade' : ($request->input('is_downgrade') ? 'downgrade' : 'new')
                ]),
                'page-template' => 'ID',
                'currency' => 'USD',
                'use-test-mode' => config('payment.gateways.PayProGlobal.test_mode', true) ? 'true' : 'false',
                'secret-key' => config('payment.gateways.PayProGlobal.webhook_secret'),
                'success-url' => $successUrl,
                'cancel-url' => route('payments.popup-cancel')
            ];

            $checkoutUrl = "https://store.payproglobal.com/checkout?" . http_build_query($checkoutParams);

            Log::info('PayProGlobal checkout created', [
                'user_id' => $user->id,
                'pending_order_id' => $pendingOrderId,
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
            return ['error' => 'Order verification failed.'];
        }

        $order = $response->json();
        $subscriptionId = $order['items'][0]['subscription'] ?? null;

        if (!$subscriptionId) {
            return ['error' => 'No subscription found for this order.'];
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
        Log::info('[handleSuccess] called', [
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
            if (empty($gateway)) {
                Log::error('No gateway specified in success callback', [
                    'params' => $request->all(),
                    'query' => $request->query(),
                    'url' => $request->fullUrl()
                ]);
                return redirect()->route('home')->with('error', 'Invalid payment gateway');
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
                    Log::error('Missing Paddle transaction_id', ['params' => $request->all()]);
                    return redirect()->route('home')->with('error', 'Invalid payment request');
                }

                Log::info('Processing Paddle success callback', [
                    'transaction_id' => $transactionId,
                    'user_authenticated' => Auth::check(),
                    'user_id' => Auth::id()
                ]);

                $order = Order::where('transaction_id', $transactionId)->first();
                if ($order && $order->status === 'completed') {
                    Log::info('Order already completed', ['transaction_id' => $transactionId]);
                    return redirect()->route('user.dashboard')->with('success', 'Subscription active');
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
                    return redirect()->route('home')->with('error', 'Payment verification failed');
                }

                $transactionData = $response->json()['data'];
                $customData = $transactionData['custom_data'] ?? [];
                $userId = $customData['user_id'] ?? null;

                if (!$userId) {
                    Log::error('No user_id in Paddle transaction custom_data', [
                        'transaction_id' => $transactionId,
                        'custom_data' => $customData
                    ]);
                    return redirect()->route('home')->with('error', 'Invalid transaction data');
                }

                Log::info('Processing Paddle payment with user_id from custom_data', [
                    'transaction_id' => $transactionId,
                    'user_id' => $userId,
                    'custom_data' => $customData
                ]);

                // Process the payment using the webhook method since we have the user_id
                $packageName = $customData['package'] ?? null;
                if (!$packageName) {
                    Log::error('No package name in Paddle transaction custom_data', [
                        'transaction_id' => $transactionId,
                        'custom_data' => $customData
                    ]);
                    return redirect()->route('home')->with('error', 'Invalid transaction data');
                }

                $result = $this->processPaddlePaymentFromWebhook($transactionData, $packageName, $userId);

                if ($result) {
                    Log::info('Paddle payment processed successfully via success callback', [
                        'transaction_id' => $transactionId,
                        'user_id' => $userId
                    ]);
                    return redirect()->route('user.dashboard')->with('success', 'Subscription activated');
                } else {
                    Log::error('Failed to process Paddle payment via success callback', [
                        'transaction_id' => $transactionId,
                        'user_id' => $userId
                    ]);
                    return redirect()->route('home')->with('error', 'Payment processing failed');
                }
            }

            if ($gateway === 'fastspring') {
                $orderId = $request->input('orderId') ?? $request->query('orderId');
                $packageName = $request->input('package_name') ?? $request->query('package_name');

                $subscriptionData = $this->getSubscriptionId($orderId); // Returns the array
                // Handle errors from getSubscriptionId
                if (isset($subscriptionData['error'])) {
                    Log::error('FastSpring Error: ' . $subscriptionData['error']);
                    return redirect()->route('home')->with('error', $subscriptionData['error']);
                }

                // Proceed if successfull
                $subscriptionId = $subscriptionData['id'];

                if (!$orderId) {
                    Log::error('Missing FastSpring orderId', ['params' => $request->all()]);
                    return redirect()->route('home')->with('error', 'Invalid order ID');
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
                    return redirect()->route('home')->with('error', 'Order verification failed');
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

                // 36908520, 36, Pro
                if (!$orderId || !$userId || !$packageName) {
                    Log::error('Missing PayProGlobal parameters', [
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'package' => $packageName,
                        'params' => $request->all()
                    ]);
                    return redirect()->route('subscription', ['package_name' => $packageName])->with('error', 'Invalid payment parameters');
                }

                $isVerified = $this->verifyPayProGlobalPayment($orderId, $orderId);
                if (!$isVerified) {
                    Log::error('PayProGlobal payment verification failed', ['order_id' => $orderId]);
                    return redirect()->route('subscription', ['package_name' => $packageName])->with('error', 'Payment verification failed');
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

            // Extract package name from request for redirect
            $packageName = $request->input('package_name') ??
                          $request->query('package_name') ??
                          $request->input('package') ??
                          $request->query('package');

            return redirect()->route('subscription', ['package_name' => $packageName])->with('error', 'Invalid gateway');
        } catch (\Exception $e) {
            Log::error('Payment success processing failed', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'url' => $request->fullUrl()
            ]);

            // Check if this is a license API failure
            if ($e->getMessage() === 'license_api_failed') {
                Log::error('License API failed during payment processing', [
                    'gateway' => $gateway ?? 'unknown',
                    'params' => $request->all()
                ]);
                return redirect()->route('payments.license-error')->with('error', 'license_api_failed');
            }

            // Extract package name from request for redirect
            $packageName = $request->input('package_name') ??
                          $request->query('package_name') ??
                          $request->input('package') ??
                          $request->query('package');

            return redirect()->route('subscription', ['package_name' => $packageName])->with('error', 'Payment processing failed');
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
                // 'status' => 'completed', // <-- Remove this for now
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

            // Always check license availability from API for paid packages, regardless of existing license
            if ($package->isFree()) {
                Log::info('Skipping license generation for free package', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'package_price' => $package->price
                ]);

                $userUpdateData = [
                    'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                    'package_id' => $package->id,
                    'subscription_starts_at' => now(),
                    'is_subscribed' => true,
                    'subscription_id' => $subscriptionId
                ];

                Log::info('Updating user for free package without license', [
                    'user_id' => $user->id,
                    'update_data' => $userUpdateData
                ]);

                $user->update($userUpdateData);

                Log::info('User updated successfully for free package', [
                    'user_id' => $user->id,
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'subscription_id' => $user->subscription_id
                ]);

                // Mark order as completed
                $order->update(['status' => 'completed']);
            } else {
                Log::info('Generating license key', [
                    'user_id' => $user->id,
                    'action' => $action
                ]);

                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('Failed to generate license key', ['user_id' => $user->id]);
                    // Do not mark as completed/subscribed, return error
                    throw new \Exception('License generation failed');
                }

                Log::info('License key generated', [
                    'user_id' => $user->id,
                    'license_key' => $licenseKey
                ]);

                $userUpdateData = [
                    'payment_gateway_id' => $this->getPaymentGatewayId($gateway),
                    'package_id' => $package->id,
                    'subscription_starts_at' => now(),
                    'license_key' => $licenseKey,
                    'is_subscribed' => true,
                    'subscription_id' => $subscriptionId
                ];

                Log::info('Updating user with license', [
                    'user_id' => $user->id,
                    'update_data' => $userUpdateData
                ]);

                $user->update($userUpdateData);

                Log::info('User updated successfully with license', [
                    'user_id' => $user->id,
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'subscription_id' => $user->subscription_id,
                    'license_key' => $user->license_key
                ]);

                // Try to add license to external API - if this fails, we need to rollback
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey, $subscriptionId);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license via API - rolling back payment process', [
                        'user_id' => $user->id,
                        'license_key' => $licenseKey,
                        'transaction_id' => $transactionId
                    ]);

                    // Rollback user changes
                    $user->update([
                        'payment_gateway_id' => null,
                        'package_id' => null,
                        'subscription_starts_at' => null,
                        'license_key' => null,
                        'is_subscribed' => false,
                        'subscription_id' => null
                    ]);

                    // Mark order as failed
                    $order->update(['status' => 'failed']);

                    // Throw exception to trigger rollback and show error
                    throw new \Exception('license_api_failed');
                }

                // Now mark order as completed
                $order->update(['status' => 'completed']);
            }

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

            return redirect()->route('user.dashboard')->with('success', $action === 'upgrade' ? 'Subscription upgraded' : 'Subscription activated');
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
        \Log::info('[handlePaddleWebhook] called', ['payload' => $request->all()]);
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

                if (!hash_equals($expectedSignature, $receivedSignature)) {
                    Log::error('Invalid Paddle webhook signature');
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
        \Log::info('[handleFastSpringWebhook] called', ['payload' => $request->all()]);
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
        Log::info('PayProGlobal Webhook Client IP', ['ip' => $request->ip()]);
        Log::info('PayProGlobal Webhook Raw Content:', [
            'content' => $request->getContent(),
            'headers' => $request->headers->all()
        ]);

        $contentType = $request->header('Content-Type');
        $payload = [];

        if (str_contains($contentType, 'application/json')) {
            $payload = $request->json()->all();
        } else {
            parse_str($request->getContent(), $payload);
        }

        Log::info('PayProGlobal Webhook Parsed Data:', ['payload' => $payload]);

        try {
            if (empty($payload)) {
                Log::error('Empty PayProGlobal webhook payload');
                return response()->json(['success' => false, 'error' => 'Empty payload'], 400);
            }
            Log::info('PayProGlobal webhook payload extracted', [
                'payload_count' => count($payload),
                'payload_keys' => array_keys($payload),
                'payload' => $payload
            ]);

            // Extract key fields from the webhook
            $orderId = $payload['ORDER_ID'] ?? null;
            $ipnType = $payload['IPN_TYPE_NAME'] ?? null;
            $orderStatus = $payload['ORDER_STATUS'] ?? null;
            $customerEmail = $payload['CUSTOMER_EMAIL'] ?? null;
            $productId = $payload['PRODUCT_ID'] ?? null;
            $orderTotal = $payload['ORDER_TOTAL_AMOUNT'] ?? null;
            $currency = $payload['ORDER_CURRENCY_CODE'] ?? null;

            Log::info('PayProGlobal webhook fields extracted', [
                'order_id' => $orderId,
                'ipn_type' => $ipnType,
                'order_status' => $orderStatus,
                'customer_email' => $customerEmail,
                'product_id' => $productId,
                'order_total' => $orderTotal,
                'currency' => $currency
            ]);

            // Check if this is an OrderCharged event
            if ($ipnType === 'OrderCharged' && $orderId) {
                Log::info('PayProGlobal OrderCharged event detected', [
                    'order_id' => $orderId,
                    'ipn_type' => $ipnType
                ]);

                // Find user by email
                $user = User::where('email', $customerEmail)->first();
                if (!$user) {
                    Log::error('PayProGlobal webhook: User not found by email', [
                        'customer_email' => $customerEmail,
                        'order_id' => $orderId
                    ]);
                    return response()->json(['success' => false, 'error' => 'User not found'], 404);
                }

                Log::info('PayProGlobal webhook: User found', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'order_id' => $orderId
                ]);

                // Find package by product ID
                $package = Package::where('payproglobal_product_id', $productId)->first();
                if (!$package) {
                    Log::error('PayProGlobal webhook: Package not found by product ID', [
                        'product_id' => $productId,
                        'order_id' => $orderId
                    ]);
                    return response()->json(['success' => false, 'error' => 'Package not found'], 404);
                }

                Log::info('PayProGlobal webhook: Package found', [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'product_id' => $productId
                ]);

                // Process the payment
                $paymentData = [
                    'order_id' => $orderId,
                    'user_id' => $user->id,
                    'package' => $package->name,
                    'amount' => $orderTotal,
                    'currency' => $currency,
                    'customer_email' => $customerEmail,
                    'product_id' => $productId,
                    'action' => 'new'
                ];

                Log::info('PayProGlobal webhook: Processing payment', [
                    'payment_data' => $paymentData
                ]);

                $result = $this->processPayment($paymentData, 'payproglobal');

                if ($result) {
                    Log::info('PayProGlobal webhook: Payment processed successfully', [
                        'order_id' => $orderId,
                        'user_id' => $user->id
                    ]);
                    return response()->json(['success' => true, 'message' => 'Payment processed', 'order_id' => $orderId], 200);
                } else {
                    Log::error('PayProGlobal webhook: Payment processing failed', [
                        'order_id' => $orderId,
                        'user_id' => $user->id
                    ]);
                    return response()->json(['success' => false, 'error' => 'Payment processing failed'], 500);
                }
            }

            Log::info('PayProGlobal webhook: Event ignored', [
                'ipn_type' => $ipnType,
                'order_id' => $orderId,
                'reason' => 'Not an OrderCharged event or missing order ID'
            ]);

            return response()->json(['success' => false, 'message' => 'Event ignored'], 200);
        } catch (\Exception $e) {
            Log::error('PayProGlobal webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this is a license API failure
            if ($e->getMessage() === 'license_api_failed') {
                Log::error('License API failed during PayProGlobal webhook processing', [
                    'payload' => $payload
                ]);
                // For webhooks, we return a 200 status to prevent retries, but log the error
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
        \Log::info('[cancelSubscription] called', ['user_id' => \Auth::id()]);
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
                return redirect()->back()->with('error', 'No active subscription to cancel');
            }

            // Check if user has subscription_id, if not, we'll just mark as cancelled in database
            if (!$user->subscription_id) {
                Log::info('User has subscription but no subscription_id, marking as cancelled in database', [
                    'user_id' => $user->id,
                    'is_subscribed' => $user->is_subscribed,
                    'package_id' => $user->package_id,
                    'payment_gateway_id' => $user->payment_gateway_id
                ]);

                // Mark subscription as cancelled in database only
                DB::transaction(function () use ($user) {
                    $user->update([
                        'is_subscribed' => 0,
                        'subscription_ends_at' => now(),
                        'subscription_starts_at' => null,
                        'package_id' => null,
                        'payment_gateway_id' => null
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

                return redirect()->back()->with('success', 'Subscription cancelled successfully');
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
                $environment = config('payment.gateways.Paddle.environment', 'sandbox');

                // Use the correct API URL based on environment
                $apiBaseUrl = $environment === 'production'
                    ? 'https://api.paddle.com'
                    : 'https://sandbox-api.paddle.com';

                Log::info('Canceling Paddle subscription', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'environment' => $environment,
                    'api_endpoint' => "{$apiBaseUrl}/subscriptions/{$subscriptionId}/cancel"
                ]);

                // Use the correct Paddle API endpoint for subscription cancellation
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])->post("{$apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
                    'effective_from' => 'immediately' // Cancel immediately instead of at end of billing period
                ]);

                if (!$response->successful()) {
                    Log::error('Paddle subscription cancellation failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'response_status' => $response->status(),
                        'response_body' => $response->body(),
                        'environment' => $environment
                    ]);
                    return redirect()->back()->with('error', 'Failed to cancel subscription');
                }

                $responseData = $response->json();
                Log::info('Paddle cancellation response', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'response_data' => $responseData,
                    'environment' => $environment
                ]);
            } else {
                Log::error('Unsupported payment gateway for cancellation', ['gateway' => $gateway, 'user_id' => $user->id]);
                return redirect()->back()->with('error', 'Unsupported payment gateway');
            }

            // Update user and order records
            DB::transaction(function () use ($user, $subscriptionId) {
                // First update the user's subscription status
                $user->update([
                    'is_subscribed' => 0,
                    'subscription_id' => null,
                    'subscription_ends_at' => now(),
                    'subscription_starts_at' => null,
                    'package_id' => null,
                    'payment_gateway_id' => null
                ]);

                // Find the most recent order for this user and update its status
                $order = Order::where('user_id', $user->id)
                    ->latest('created_at')
                    ->first();

                if ($order) {
                    $order->update(['status' => 'canceled']);
                    Log::info('Updated order status to canceled', [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId
                    ]);
                } else {
                    Log::warning('No order found for user when canceling subscription', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId
                    ]);
                }
            });

            Log::info('Subscription cancellation completed', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId
            ]);

            // Debug response decision
            Log::info('Response decision', [
                'wantsJson' => $request->wantsJson(),
                'accept' => $request->header('Accept'),
                'willReturnJson' => $request->wantsJson()
            ]);

            if ($request->wantsJson()) {
                Log::info('Returning JSON response');
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription canceled successfully'
                ]);
            }

            Log::info('Returning redirect response');
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

            // Process the payment
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

            $user = User::where('subscription_id', $subscriptionId)->first();
            if ($user) {
                $user->update([
                    'is_subscribed' => false,
                    'subscription_ends_at' => now(),
                    'subscription_id' => null
                ]);

                Log::info('Processed subscription cancellation from Paddle webhook', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId
                ]);
            }

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('Error handling Paddle cancellation webhook', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
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

            // Always check license availability from API for paid packages, regardless of existing license
            if ($package->isFree()) {
                Log::info('Skipping license generation for free package from webhook', [
                    'user_id' => $user->id,
                    'package_name' => $package->name,
                    'package_price' => $package->price
                ]);
            } else {
                Log::info('Generating license key from webhook', [
                    'user_id' => $user->id
                ]);

                $licenseKey = $this->makeLicense($user, true);
                if ($licenseKey) {
                    Log::info('License key generated from webhook', [
                        'user_id' => $user->id,
                        'license_key' => $licenseKey
                    ]);

                    // Try to add license to external API - if this fails, we need to rollback
                    $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                    if (!$licenseApiSuccess) {
                        Log::error('Failed to add license via API from webhook - rolling back payment process', [
                            'user_id' => $user->id,
                            'license_key' => $licenseKey,
                            'transaction_id' => $transactionId
                        ]);

                        // Rollback user changes
                        $user->update([
                            'payment_gateway_id' => null,
                            'package_id' => null,
                            'subscription_starts_at' => null,
                            'license_key' => null,
                            'is_subscribed' => false,
                            'subscription_id' => null
                        ]);

                        // Mark order as failed
                        $order->update(['status' => 'failed']);

                        // Throw exception to trigger rollback and show error
                        throw new \Exception('license_api_failed');
                    }
                } else {
                    Log::error('Failed to generate license key from webhook', ['user_id' => $user->id]);
                    throw new \Exception('License generation failed');
                }
            }

            $userUpdateData = [
                'payment_gateway_id' => $this->getPaymentGatewayId('paddle'),
                'package_id' => $package->id,
                'subscription_starts_at' => now(),
                'is_subscribed' => true,
                'subscription_id' => $transactionData['subscription_id'] ?? null
            ];

            Log::info('Updating user from webhook', [
                'user_id' => $user->id,
                'update_data' => $userUpdateData
            ]);

            // Update user subscription
            $user->update($userUpdateData);

            Log::info('User updated successfully from webhook', [
                'user_id' => $user->id,
                'is_subscribed' => $user->is_subscribed,
                'package_id' => $user->package_id,
                'subscription_id' => $user->subscription_id,
                'payment_gateway_id' => $user->payment_gateway_id,
                'subscription_starts_at' => $user->subscription_starts_at
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
        \Log::info('[upgradeToPackage] called', ['package' => $package, 'user_id' => \Auth::id()]);
        Log::info('Package upgrade requested', ['package' => $package, 'user_id' => Auth::id()]);

        try {
            $processedPackage = $this->processPackageName($package);
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // For paid packages, check license availability from API and update user's license
            if (!$packageData->isFree()) {
                Log::info('Checking license availability from API for paid package upgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'package_price' => $packageData->price
                ]);

                // Get license from API (bypass cache for fresh license)
                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('License not available from API - blocking upgrade', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Try to add license to external API first
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API - blocking upgrade', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name,
                        'license_key' => $licenseKey
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Only update user's license in database after successful external API call
                $user->update(['license_key' => $licenseKey]);

                Log::info('License successfully added to external API and updated in user table for upgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'license_key' => $licenseKey
                ]);
            } else {
                Log::info('Skipping license check for free package upgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
            }

            // Check if user has an active subscription
            if (!$user->is_subscribed || !$user->subscription_id) {
                return response()->json(['error' => 'No active subscription to upgrade'], 400);
            }

            // Check if user has a payment gateway
            if (!$user->payment_gateway_id) {
                return response()->json(['error' => 'No payment gateway associated with subscription'], 400);
            }

            $gateway = $user->paymentGateway;
            if (!$gateway) {
                return response()->json(['error' => 'Payment gateway not found'], 400);
            }

            // Handle upgrade based on gateway
            if ($gateway->name === 'Paddle') {
                return $this->handlePaddleUpgrade($user, $packageData);
            } elseif ($gateway->name === 'FastSpring') {
                return $this->handleFastSpringUpgrade($user, $packageData);
            } elseif ($gateway->name === 'Pay Pro Global') {
                return $this->handlePayProGlobalUpgrade($user, $packageData);
            } else {
                return response()->json(['error' => 'Unsupported payment gateway for upgrade'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Package upgrade error', [
                'error' => $e->getMessage(),
                'package' => $package,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Upgrade failed'], 500);
        }
    }

    private function handlePaddleUpgrade($user, $packageData)
    {
        try {
            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for upgrade');
                return response()->json(['error' => 'Payment configuration error'], 500);
            }

            // Get the new price ID for the package
            $productsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Paddle products fetch failed for upgrade', ['status' => $productsResponse->status()]);
                return response()->json(['error' => 'Product fetch failed'], 500);
            }

            $products = $productsResponse->json()['data'];
            $matchingProduct = collect($products)->firstWhere('name', $packageData->name);

            if (!$matchingProduct) {
                Log::error('Paddle product not found for upgrade', ['package' => $packageData->name]);
                return response()->json(['error' => 'Unavailable package'], 400);
            }

            $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
            if (!$price) {
                Log::error('No active prices found for upgrade', ['product_id' => $matchingProduct['id']]);
                return response()->json(['error' => 'No active price'], 400);
            }

            // Update the subscription
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->patch("{$apiBaseUrl}/subscriptions/{$user->subscription_id}", [
                'items' => [
                    [
                        'price_id' => $price['id'],
                        'quantity' => 1
                    ]
                ],
                'proration_billing_mode' => 'prorated_immediately'
            ]);

            if (!$response->successful()) {
                Log::error('Paddle subscription upgrade failed', [
                    'subscription_id' => $user->subscription_id,
                    'response' => $response->body()
                ]);
                return response()->json(['error' => 'Upgrade failed'], 500);
            }

            // Update user record
            $user->update([
                'package_id' => $packageData->id,
                'subscription_starts_at' => now()
            ]);

            Log::info('Paddle subscription upgraded successfully', [
                'user_id' => $user->id,
                'subscription_id' => $user->subscription_id,
                'new_package' => $packageData->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle upgrade error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Upgrade failed'], 500);
        }
    }

    private function handleFastSpringUpgrade($user, $packageData)
    {
        // FastSpring upgrade logic would go here
        // This would typically involve creating a new order for the upgrade
        return response()->json(['error' => 'FastSpring upgrade not yet implemented'], 501);
    }

    private function handlePayProGlobalUpgrade($user, $packageData)
    {
        // PayProGlobal upgrade logic would go here
        return response()->json(['error' => 'PayProGlobal upgrade not yet implemented'], 501);
    }

    public function downgradeSubscription(Request $request)
    {
        Log::info('Package downgrade requested', ['user_id' => Auth::id()]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
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
                return response()->json(['error' => 'Invalid package selected'], 400);
            }

            // For paid packages, check license availability from API and update user's license
            if (!$packageData->isFree()) {
                Log::info('Checking license availability from API for paid package downgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'package_price' => $packageData->price
                ]);

                // Get license from API (bypass cache for fresh license)
                $licenseKey = $this->makeLicense($user, true);
                if (!$licenseKey) {
                    Log::error('License not available from API - blocking downgrade', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Try to add license to external API first
                $licenseApiSuccess = $this->addLicenseToExternalAPI($user, $licenseKey);
                if (!$licenseApiSuccess) {
                    Log::error('Failed to add license to external API - blocking downgrade', [
                        'user_id' => $user->id,
                        'package_name' => $packageData->name,
                        'license_key' => $licenseKey
                    ]);
                    return response()->json(['error' => 'License not available at the moment. Please try again later.'], 503);
                }

                // Only update user's license in database after successful external API call
                $user->update(['license_key' => $licenseKey]);

                Log::info('License successfully added to external API and updated in user table for downgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name,
                    'license_key' => $licenseKey
                ]);
            } else {
                Log::info('Skipping license check for free package downgrade', [
                    'user_id' => $user->id,
                    'package_name' => $packageData->name
                ]);
            }

            // Check if user has an active subscription
            if (!$user->is_subscribed || !$user->subscription_id) {
                return response()->json(['error' => 'No active subscription to downgrade'], 400);
            }

            // Check if user has a payment gateway
            if (!$user->payment_gateway_id) {
                return response()->json(['error' => 'No payment gateway associated with subscription'], 400);
            }

            $gateway = $user->paymentGateway;
            if (!$gateway) {
                return response()->json(['error' => 'Payment gateway not found'], 400);
            }

            // Handle downgrade based on gateway
            if ($gateway->name === 'Paddle') {
                return $this->handlePaddleDowngrade($user, $packageData);
            } elseif ($gateway->name === 'FastSpring') {
                return $this->handleFastSpringDowngrade($user, $packageData);
            } elseif ($gateway->name === 'Pay Pro Global') {
                return $this->handlePayProGlobalDowngrade($user, $packageData);
            } else {
                return response()->json(['error' => 'Unsupported payment gateway for downgrade'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Package downgrade error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Downgrade failed'], 500);
        }
    }

    private function handlePaddleDowngrade($user, $packageData)
    {
        try {
            $apiKey = config('payment.gateways.Paddle.api_key');
            $environment = config('payment.gateways.Paddle.environment', 'sandbox');
            $apiBaseUrl = $environment === 'production'
                ? 'https://api.paddle.com'
                : 'https://sandbox-api.paddle.com';

            if (empty($apiKey)) {
                Log::error('Paddle API key missing for downgrade');
                return response()->json(['error' => 'Payment configuration error'], 500);
            }

            // Get the new price ID for the package
            $productsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiBaseUrl}/products", ['include' => 'prices']);

            if (!$productsResponse->successful()) {
                Log::error('Paddle products fetch failed for downgrade', ['status' => $productsResponse->status()]);
                return response()->json(['error' => 'Product fetch failed'], 500);
            }

            $products = $productsResponse->json()['data'];
            $matchingProduct = collect($products)->firstWhere('name', $packageData->name);

            if (!$matchingProduct) {
                Log::error('Paddle product not found for downgrade', ['package' => $packageData->name]);
                return response()->json(['error' => 'Unavailable package'], 400);
            }

            $price = collect($matchingProduct['prices'])->firstWhere('status', 'active');
            if (!$price) {
                Log::error('No active prices found for downgrade', ['product_id' => $matchingProduct['id']]);
                return response()->json(['error' => 'No active price'], 400);
            }

            // Update the subscription
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->patch("{$apiBaseUrl}/subscriptions/{$user->subscription_id}", [
                'items' => [
                    [
                        'price_id' => $price['id'],
                        'quantity' => 1
                    ]
                ],
                'proration_billing_mode' => 'prorated_immediately'
            ]);

            if (!$response->successful()) {
                Log::error('Paddle subscription downgrade failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return response()->json(['error' => 'Downgrade failed'], 500);
            }

            // Update user's package in database
            $user->update([
                'package_id' => $packageData->id
            ]);

            Log::info('Paddle subscription downgraded successfully', [
                'user_id' => $user->id,
                'new_package' => $packageData->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription downgraded successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Paddle downgrade error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Downgrade failed'], 500);
        }
    }

    private function handleFastSpringDowngrade($user, $packageData)
    {
        // FastSpring downgrade logic would go here
        return response()->json(['error' => 'FastSpring downgrade not implemented yet'], 501);
    }

    private function handlePayProGlobalDowngrade($user, $packageData)
    {
        // PayProGlobal downgrade logic would go here
        return response()->json(['error' => 'PayProGlobal downgrade not implemented yet'], 501);
    }

    public function verifyOrder(Request $request, string $transactionId)
    {
        \Log::info('[verifyOrder] called', [
            'transaction_id' => $transactionId,
            'user_id' => \Auth::id(),
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
        \Log::info('[handleCancel] called', [
            'params' => $request->all(),
            'query' => $request->query(),
            'url' => $request->fullUrl()
        ]);
        Log::info('Payment cancelled', [
            'params' => $request->all(),
            'query' => $request->query(),
            'url' => $request->fullUrl()
        ]);

        return redirect()->route('home')->with('info', 'Payment was cancelled');
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
}
