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

            $package = \App\Models\Package::where('name', $processedPackage)->first();

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unavailable package',
                    'message' => 'This package is not available for purchase',
                    'available_packages' => \App\Models\Package::pluck('name')->toArray()
                ], 400);
            }

            // Get payment gateway ID
            $paymentGatewayId = $this->getPaymentGatewayId('paddle');

            // Update user's package_id, payment_gateway_id, and subscription_starts_at
            $user->package_id = $package->id;
            if ($paymentGatewayId) {
                $user->payment_gateway_id = $paymentGatewayId;
            }
            $user->subscription_starts_at = now();
            $user->save();

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
        // First, try to find existing customer by email
        $searchResponse = Http::withHeaders($headers)
            ->get('https://sandbox-api.paddle.com/customers', [
                'email' => $user->email
            ]);

        if ($searchResponse->successful()) {
            $customers = $searchResponse->json()['data'] ?? [];
            if (!empty($customers)) {
                return $customers[0]['id'];
            }
        }

        // Create new customer
        $customerResponse = Http::withHeaders($headers)
            ->post('https://sandbox-api.paddle.com/customers', [
                'email' => $user->email,
                'name' => $user->name,
                'custom_data' => [
                    'customer_reference_id' => (string) $user->id
                ],
                'locale' => 'en'
            ]);

        if ($customerResponse->successful()) {
            $customerData = $customerResponse->json();
            return $customerData['data']['id'];
        }

        // Handle customer already exists error
        $errorBody = $customerResponse->body();
        $errorData = json_decode($errorBody, true);

        if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'customer_already_exists') {
            $detail = $errorData['error']['detail'] ?? '';
            if (preg_match('/customer of id (ctm_[a-zA-Z0-9]+)/', $detail, $matches)) {
                return $matches[1];
            }
        }

        \Log::error('Failed to create Paddle customer', [
            'user_id' => $user->id,
            'error' => $errorData
        ]);

        return null;

    } catch (\Exception $e) {
        \Log::error('Exception in getOrCreatePaddleCustomer', [
            'user_id' => $user->id,
            'message' => $e->getMessage()
        ]);
        return null;
    }
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

            // Get package from database and update user's package_id
            $packageModel = \App\Models\Package::where('name', $processedPackage)->first();

            if (!$packageModel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unavailable package',
                    'message' => 'This package is not available for purchase',
                    'available_packages' => \App\Models\Package::pluck('name')->toArray()
                ], 400);
            }

            // Get payment gateway ID
            $paymentGatewayId = $this->getPaymentGatewayId('fastspring');

            // Update user's package_id, payment_gateway_id, and subscription_starts_at
            $user->package_id = $packageModel->id;
            if ($paymentGatewayId) {
                $user->payment_gateway_id = $paymentGatewayId;
            }
            $user->subscription_starts_at = now();
            $user->save();

            // Get product ID from config
            $productId = config("payment.gateways.FastSpring.product_ids.{$processedPackage}");

            if ($productId === null) {
                if ($processedPackage === 'free') {
                    try {
                        return response()->json([
                            'success' => true,
                            'message' => 'Free plan activated',
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
                'returnUrl' => route('payment.success') . '?source=fastspring',
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
        try {
            // Normalize package name
            $processedPackage = str_replace('-plan', '', strtolower($package));

            // Validate package and user
            $validation = $this->validatePackageAndGetUser($processedPackage);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // Get package from database and update user's package_id
            $packageModel = \App\Models\Package::where('name', $processedPackage)->first();

            if (!$packageModel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unavailable package',
                    'message' => 'This package is not available for purchase',
                    'available_packages' => \App\Models\Package::pluck('name')->toArray()
                ], 400);
            }

            // Get payment gateway ID
            $paymentGatewayId = $this->getPaymentGatewayId('payproglobal');

            // Update user's package_id, payment_gateway_id, and subscription_starts_at
            $user->package_id = $packageModel->id;
            if ($paymentGatewayId) {
                $user->payment_gateway_id = $paymentGatewayId;
            }
            $user->subscription_starts_at = now();
            $user->save();

            // Handle free plan
            if ($processedPackage === 'free') {
                return response()->json([
                    'success' => true,
                    'message' => 'Free plan activated',
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
            $checkoutUrl .= "&page-template=popup";
            $checkoutUrl .= "&use-test-mode=true";
            $checkoutUrl .= "&secret-key=" . urlencode($secretKey);

            return response()->json([
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => is_string($packageData->features)
                        ? json_decode($packageData->features)
                        : $packageData->features
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("PayProGlobal checkout failed for {$processedPackage}: " . $e->getMessage());
            return response()->json([
                'error' => 'Checkout processing failed',
                'message' => $e->getMessage()
            ], 500);
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
                'retry_url' => $passthrough['checkout_url'] ?? route('pricing')
            ];

            // 3. If logged in user, offer personalized options
            $user = auth()->user() ?? User::find($passthrough['user_id'] ?? null);

            // 4. Show cancellation page with helpful options
            return view('payment.cancel', [
                'reason' => $request->reason ?? 'payment_cancelled',
                'options' => [
                    'Try again' => $cancellationData['retry_url'],
                    'Contact support' => route('support'),
                    'Choose different plan' => route('pricing'),
                    'Payment questions' => route('faq.payment')
                ],
                'user' => $user,
                'contact_email' => config('app.support_email')
            ]);
        } catch (\Exception $e) {
            Log::error('Cancel handling failed: ' . $e->getMessage());
            return redirect()->route('pricing')->with(
                'error',
                'Your payment was cancelled. Please try again or contact support.'
            );
        }
    }

    private function activateUserSubscription($user, $package, $orderId)
    {
        DB::transaction(function () use ($user, $package, $orderId) {
            // 1. Update user's subscription
            $user->update([
                'package' => $package,
                'subscription_ends_at' => now()->addMonth(),
                'last_payment_id' => $orderId
            ]);
        });
    }
}
