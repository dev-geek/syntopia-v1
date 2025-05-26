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
    /**
     * Validate the package name and get the user and package data
     *
     * @param string $package
     * @return array|response
     */
    private function validatePackageAndGetUser($package)
    {
        // Get package data from database
        $packageModel = Package::where('name', $package)->first();

        // Check if package exists
        if (!$packageModel) {
            return response()->json(['error' => 'Invalid package'], 400);
        }

        // Get the authenticated user
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
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
            // Add logging to debug
            \Log::info('Paddle checkout called for package: ' . $package);

            $package = str_replace('-plan', '', strtolower($package));

            $validation = $this->validatePackageAndGetUser($package);
            if (!is_array($validation)) {
                return $validation; // error response
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // Check if user has a name
            if (empty($user->name)) {
                \Log::error('User name is missing for user ID: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'error' => 'User information incomplete',
                    'message' => 'Your account must have a name to proceed with the purchase.'
                ], 400);
            }

            $productIds = $this->getProductIds('paddle');
            $productId = $productIds[$package] ?? null;

            if (!$productId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unavailable package',
                    'message' => 'This package is not available for purchase'
                ], 400);
            }

            // Calculate billing period
            $startsAt = now()->format('Y-m-d\TH:i:s\Z');
            $endsAt = now()->addYear()->subSecond()->format('Y-m-d\TH:i:s\Z');

            $actualPriceId = 'pri_01jvyvt5hs48d5gd85m54nv0a6';

            // Prepare transaction data for Paddle API
            $transactionData = [
                'items' => [
                    [
                        'quantity' => 1,
                        'price_id' => $actualPriceId,
                    ],
                ],

                'customer' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'currency_code' => 'USD',
                'collection_mode' => 'manual',
                'billing_details' => [
                    'enable_checkout' => true,
                    'purchase_order_number' => 'PO-' . uniqid(),
                    'payment_terms' => [
                        'interval' => 'day',
                        'frequency' => 14,
                    ],
                ],
                'billing_period' => [
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
            ];

            $apiKey = config('payment.gateways.Paddle.api_key');

            \Log::info('API Key format check: ' . substr($apiKey, 0, 10) . '...');

            // Make request to Paddle API with corrected header format
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post('https://sandbox-api.paddle.com/transactions', $transactionData);

            // Log the request details for debugging
            \Log::info('Request URL: https://sandbox-api.paddle.com/transactions');
            \Log::info('Request data: ' . json_encode($transactionData));
            \Log::info('Response status: ' . $response->status());
            \Log::info('Response headers: ' . json_encode($response->headers()));

            if (!$response->successful()) {
                \Log::error('Paddle API error: ' . $response->body());
                \Log::error('Response status: ' . $response->status());

                return response()->json([
                    'success' => false,
                    'error' => 'Paddle API error',
                    'message' => 'Failed to create transaction: ' . $response->body(),
                    'status_code' => $response->status()
                ], 400);
            }

            $paddleResponse = $response->json();
            \Log::info('Paddle response: ' . json_encode($paddleResponse));

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $paddleResponse['data']['id'],
                    'checkout_url' => $paddleResponse['data']['checkout']['url'] ?? null,
                    'items' => $transactionData['items'],
                    'customer_id' => Auth::user()->id,
                    'currency_code' => $transactionData['currency_code'],
                    'collection_mode' => $transactionData['collection_mode'],
                    'billing_details' => $transactionData['billing_details'],
                    'billing_period' => $transactionData['billing_period'],
                    'settings' => [
                        'theme' => 'light',
                        'allow_logout' => true,
                        'show_add_discounts' => true,
                        'allow_discount_removal' => true,
                        'display_mode' => 'inline',
                        'locale' => 'en',
                        'show_add_tax_id' => true,
                        'variant' => 'one-page',
                        'allowed_payment_methods' => ['card', 'paypal'],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Paddle checkout exception: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Checkout failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fastspringCheckout(Request $request, $package)
    {
        try {
            // Remove "-plan" suffix if present
            $package = str_replace('-plan', '', $package);
            $package = strtolower($package); // Normalize package name to lowercase

            $validation = $this->validatePackageAndGetUser($package);
            if (!is_array($validation)) {
                return $validation; // Returns the error response
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];
            $productIds = $this->getProductIds('fastspring');
            $productId = $productIds[$package] ?? null;

            // For free or enterprise plans that don't use direct checkout
            if ($productId === null) {
                if ($package === 'free') {
                    // Handle free plan activation logic
                    try {
                        // Update user's subscription in database
                        // $user->updateSubscription('free', $packageData->id);

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
                } elseif ($package === 'enterprise') {
                    // Return a contact form URL or other appropriate action
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
            }

            // Get FastSpring store configuration
            $storeId = Config::get('payment.gateways.fastspring.store_id');
            if (!$storeId) {
                throw new \Exception('FastSpring store ID not configured');
            }

            $environment = Config::get('payment.gateways.fastspring.environment', 'production');
            $builderUrl = $environment === 'test'
                ? 'https://test-builder.fastspring.com'
                : 'https://builder.fastspring.com';

            // Create a secure hash for validating the order if your implementation requires it
            $secureHash = hash_hmac(
                'sha256',
                $user->id . $package . time(),
                Config::get('payment.gateways.FastSpring.api_secret', '')
            );

            // Prepare the necessary data for FastSpring checkout
            $checkoutData = [
                'storeId' => $storeId,
                'productPath' => $productId,
                'builderUrl' => $builderUrl,
                'tags' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'package' => $package,
                    'secure_hash' => $secureHash
                ],
                'payloadData' => [
                    'items' => [
                        [
                            'path' => $productId,
                            'quantity' => 1
                        ]
                    ],
                    'contact' => [
                        'email' => $user->email
                    ],
                    'tags' => [
                        'user_id' => $user->id,
                        'package' => $package
                    ]
                ]
            ];

            // Prepare success URLs and callback URLs
            $checkoutData['successUrl'] = route('payment.success') . '?source=fastspring';
            $checkoutData['cancellationUrl'] = route('payment.cancel') . '?source=fastspring';

            return response()->json([
                'success' => true,
                'checkout_data' => $checkoutData,
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
            $package = str_replace('-plan', '', strtolower($package));

            // Validate package and user
            $validation = $this->validatePackageAndGetUser($package);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];

            // Handle free plan
            if ($package === 'free') {
                // $user->updateSubscription('free', $packageData->id);
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
            if ($package === 'enterprise') {
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

            // Get product ID for paid plans
            $productIds = [
                'starter' => Config::get('payment.gateways.PayProGlobal.product_id_starter'),
                'pro' => Config::get('payment.gateways.PayProGlobal.product_id_pro'),
                'business' => Config::get('payment.gateways.PayProGlobal.product_id_business'),
            ];

            $productId = $productIds[$package] ?? null;

            if (!$productId) {
                throw new \Exception("Product ID not configured for package: {$package}");
            }

            // Generate PayProGlobal checkout URL with popup support
            $checkoutUrl = "https://store.payproglobal.com/checkout?products[1][id]={$productId}";
            $checkoutUrl .= "&email=" . urlencode($user->email);
            $checkoutUrl .= "&products[0][id]=" . $productId;
            $checkoutUrl .= "&custom=" . urlencode(json_encode([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'package' => $package,
            ]));
            $checkoutUrl .= "&first_name=" . urlencode($user->first_name ?? '');
            $checkoutUrl .= "&last_name=" . urlencode($user->last_name ?? '');
            $checkoutUrl .= "&page-template=popup";  // Critical for popup compatibility

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
        } catch (\Exception $e) {
            Log::error("PayProGlobal checkout failed for {$package}: " . $e->getMessage());
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
