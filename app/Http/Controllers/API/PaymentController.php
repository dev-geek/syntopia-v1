<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\Package; // Add Package model
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    public function paddleCheckout(Request $request, $package)
    {
        try {

            $validation = $this->validatePackageAndGetUser($package);
            if (!is_array($validation)) {
                return $validation;
            }

            $user = $validation['user'];
            $packageData = $validation['packageData'];
            $productIds = $this->getProductIds('paddle');
            $productId = $productIds[$package] ?? null;

            if (!$productId) {
                return response()->json([
                    'error' => 'This package is not available for purchase',
                    'message' => 'Product ID not found for package: ' . $package
                ], 400);
            }

            $vendorId = config('payment.gateways.Paddle.vendor_id');
            if (!$vendorId) {
                throw new \Exception('Paddle vendor ID not configured');
            }

            $checkoutBase = config('payment.gateways.Paddle.environment') === 'sandbox'
                ? 'https://sandbox-checkout.paddle.com'
                : 'https://checkout.paddle.com';

            $checkoutUrl = "{$checkoutBase}/product/{$productId}?" . http_build_query([
                'guest_email' => $user->email,
                'vendor' => config('payment.gateways.Paddle.vendor_id'),
                'passthrough' => json_encode([
                    'user_id' => $user->id,
                    'package' => $package
                ]),
                'success_url' => route('payment.success'),
                'cancel_url' => route('payment.cancel'),
                'display_mode' => 'inline', // Better UX than overlay
                'paddle_js' => 'true',
                'paddlejs-version' => '1.2.2'
            ]);

            return response()->json([
                'checkoutUrl' => $checkoutUrl,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => is_array($packageData->features)
                        ? $packageData->features
                        : json_decode($packageData->features, true)
                ],
                'environment' => config('payment.gateways.Paddle.environment')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a checkout URL for FastSpring
     *
     * @param Request $request
     * @param string $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function fastspringCheckout(Request $request, $package)
    {
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

        // For FastSpring, we typically return the product path to be used by the JS SDK
        // FastSpring checkout is handled on the frontend
        try {
            return response()->json([
                'productPath' => $productId,
                'success' => true,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => json_decode($packageData->features)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('FastSpring checkout error: ' . $e->getMessage());
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
        // Remove "-plan" suffix if present
        $package = str_replace('-plan', '', $package);
        $package = strtolower($package); // Normalize package name to lowercase

        $validation = $this->validatePackageAndGetUser($package);
        if (!is_array($validation)) {
            return $validation; // Returns the error response
        }

        $user = $validation['user'];
        $packageData = $validation['packageData'];
        $productIds = $this->getProductIds('payproglobal');
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

        // For paid plans, generate a PayProGlobal checkout URL
        try {
            // In a real implementation, you would use PayProGlobal's API
            // This is a simplified example
            $merchantId = Config::get('payment.gateways.payproglobal.merchant_id');
            $checkoutUrl = "https://secure.payproglobal.com/orderpage.aspx";
            $checkoutUrl .= "?pid=" . urlencode($productId);
            $checkoutUrl .= "&mid=" . urlencode($merchantId);
            $checkoutUrl .= "&email=" . urlencode($user->email);
            $checkoutUrl .= "&custom=" . urlencode(json_encode([
                'user_id' => $user->id,
                'package_id' => $packageData->id,
                'package' => $package
            ]));

            return response()->json([
                'checkoutUrl' => $checkoutUrl,
                'package_details' => [
                    'name' => $packageData->name,
                    'price' => $packageData->price,
                    'duration' => $packageData->duration,
                    'features' => json_decode($packageData->features)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate PayProGlobal checkout URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleSuccess(Request $request)
    {
        try {
            // 1. Basic validation
            $validated = $request->validate([
                'checkout_id' => 'required|string',
                'order_id' => 'nullable|string',
                'transaction_id' => 'nullable|string'
            ]);

            // 2. Verify with Paddle API
            $verificationResponse = $this->verifyPaddlePayment($validated['checkout_id']);

            if (!$verificationResponse['success']) {
                throw new \Exception("Payment verification failed: " . ($verificationResponse['message'] ?? 'Unknown error'));
            }

            // 3. Get user from passthrough data
            $passthrough = json_decode($request->passthrough ?? '{}', true);
            $userId = $passthrough['user_id'] ?? null;
            $package = $passthrough['package'] ?? null;

            if (!$userId) {
                throw new \Exception("User information missing in payment data");
            }

            // 4. Process the successful payment
            $user = User::findOrFail($userId);
            $this->activateUserSubscription($user, $package, $validated['order_id']);

            // 5. Prepare success data
            $orderDetails = [
                'order_id' => $validated['order_id'],
                'transaction_id' => $validated['transaction_id'],
                'package' => $package,
                'amount' => $verificationResponse['amount'] ?? null,
                'date' => now()->format('F j, Y')
            ];


            // 6. Show success page with all needed info
            return view('payment.success', [
                'order' => $orderDetails,
                'user' => $user,
                // 'next_steps' => [
                //     'Access your dashboard' => route('/'),
                //     'Download invoice' => route('invoice.show', $validated['order_id']),
                //     'Get started guide' => route('guides.getting-started')
                // ]
            ]);
        } catch (\Exception $e) {
            Log::error('Payment success handling failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('payment.verification-pending', [
                'error' => $e->getMessage(),
                'contact_support' => true,
                'order_id' => $request->order_id ?? 'unknown'
            ]);
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

    private function verifyPaddlePayment($checkoutId)
    {
        try {
            $paddle = new Paddle(
                config('payment.gateways.Paddle.vendor_id'),
                config('payment.gateways.Paddle.api_key')
            );

            $response = $paddle->getOrderDetails($checkoutId);

            return [
                'success' => $response['status'] === 'completed',
                'amount' => $response['total'],
                'currency' => $response['currency'],
                'message' => $response['status'] === 'completed' ? 'Verified' : $response['status']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Verification service unavailable'
            ];
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
