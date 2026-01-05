<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\{
    User,
    Package,
    Order,
    UserLicence
};
use App\Services\{
    License\LicenseApiService,
    FirstPromoterService,
    TenantAssignmentService
};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayProGlobalPaymentGateway implements PaymentGatewayInterface
{
    private string $apiSecretKey;
    private string $vendorAccountId;
    private string $webhookSecret;
    private array $productIds;
    private bool $testMode;
    private array $endpoints;
    private ?User $user = null;
    private ?Order $order = null;

    public function __construct(
        private LicenseApiService $licenseApiService,
        private FirstPromoterService $firstPromoterService,
        private TenantAssignmentService $tenantAssignmentService,
    ) {
        $this->apiSecretKey = (string) config('payment.gateways.PayProGlobal.api_secret_key', '');
        $this->vendorAccountId = (string) config('payment.gateways.PayProGlobal.vendor_account_id', '');
        $this->webhookSecret = (string) config('payment.gateways.PayProGlobal.webhook_secret', '');
        $this->productIds = (array) config('payment.gateways.PayProGlobal.product_ids', []);
        $this->testMode = (bool) config('payment.gateways.PayProGlobal.test_mode', true);
        $this->endpoints = (array) config('payment.gateways.PayProGlobal.endpoints', []);
    }

    public function setUser(User $user): PaymentGatewayInterface
    {
        $this->user = $user;

        return $this;
    }

    public function setOrder(Order $order): PaymentGatewayInterface
    {
        $this->order = $order;

        return $this;
    }

    public function processPayment(array $paymentData, bool $returnRedirect = true): array
    {
        return $this->createCheckout($paymentData, $returnRedirect);
    }

    private function createCheckout(array $paymentData, bool $returnRedirect = true): array
    {
        Log::info('[PayProGlobalPaymentGateway::createCheckout] called', [
            'paymentData' => $paymentData,
            'returnRedirect' => $returnRedirect
        ]);

        if (!$this->user) {
            return [
                'success' => false,
                'error' => 'User context not set for checkout'
            ];
        }

        if (!$this->order) {
            return [
                'success' => false,
                'error' => 'Order context not set for checkout'
            ];
        }

        $packageName = $paymentData['package'] ?? null;
        if (!$packageName) {
            return [
                'success' => false,
                'error' => 'Package name is required'
            ];
        }

        $package = Package::whereRaw('LOWER(name) = ?', [strtolower($packageName)])->first();
        if (!$package) {
            return [
                'success' => false,
                'error' => 'Package not found'
            ];
        }

        $processedPackage = strtolower(str_replace([' ', '-'], '', $packageName));
        $productId = $this->getProductIdForPackage($package);
        if (!$productId) {
            return [
                'success' => false,
                'error' => 'Product ID not found for package'
            ];
        }

        $isUpgrade = (bool) ($paymentData['is_upgrade'] ?? false);
        $isDowngrade = (bool) ($paymentData['is_downgrade'] ?? false);
        $action = $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new');

        // Generate a temporary authentication token for cross-domain redirect
        $authToken = Str::random(64);
        // Store token in cache for 10 minutes with user_id
        Cache::put("paypro_auth_token_{$authToken}", $this->user->id, now()->addMinutes(10));

        $successParams = [
            'gateway' => 'payproglobal',
            'user_id' => $this->user->id,
            'package' => $processedPackage,
            'popup' => 'true',
            'pending_order_id' => $this->order->transaction_id,
            'action' => $action,
            'auth_token' => $authToken
        ];

        $successUrl = url(route('payments.success', $successParams));

        $customData = [
            'user_id' => $this->user->id,
            'package_id' => $package->id,
            'package' => $processedPackage,
            'pending_order_id' => $this->order->transaction_id,
            'action' => $action
        ];

        if (isset($paymentData['fp_tid']) && $paymentData['fp_tid']) {
            $customData['fp_tid'] = $paymentData['fp_tid'];
        }

        $checkoutParams = [
            'products[1][id]' => $productId,
            'email' => $this->user->email,
            'first_name' => $this->user->first_name ?? '',
            'last_name' => $this->user->last_name ?? '',
            'custom' => json_encode($customData),
            'page-template' => 'ID',
            'currency' => 'USD',
            'use-test-mode' => $this->testMode ? 'true' : 'false',
            'secret-key' => $this->webhookSecret,
            'success-url' => $successUrl,
            'cancel-url' => route('subscription')
        ];

        $checkoutUrl = 'https://store.payproglobal.com/checkout?' . http_build_query($checkoutParams);

        Log::info('[PayProGlobalPaymentGateway::createCheckout] Checkout URL generated', [
            'user_id' => $this->user->id,
            'package' => $processedPackage,
            'product_id' => $productId,
            'pending_order_id' => $this->order->transaction_id,
            'action' => $action,
        ]);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'pending_order_id' => $this->order->transaction_id,
        ];
    }

    public function handleUpgrade(array $paymentData, bool $returnRedirect = true): array
    {
        if (!$this->user) {
            return ['success' => false, 'error' => 'User context not set for upgrade'];
        }

        $activeLicense = $this->user->userLicence;
        if (!$activeLicense || !$activeLicense->subscription_id) {
            Log::warning('[PayProGlobalPaymentGateway::handleUpgrade] No subscription ID found, falling back to checkout', [
                'user_id' => $this->user->id,
            ]);
            $paymentData['is_upgrade'] = true;
            return $this->createCheckout($paymentData, $returnRedirect);
        }

        return $this->changeSubscriptionProduct($paymentData, 'upgrade', $activeLicense->subscription_id);
    }

    public function handleDowngrade(array $paymentData, bool $returnRedirect = true): array
    {
        if (!$this->user) {
            return ['success' => false, 'error' => 'User context not set for downgrade'];
        }

        $activeLicense = $this->user->userLicence;
        if (!$activeLicense || !$activeLicense->subscription_id) {
            return ['success' => false, 'error' => 'No active subscription found to downgrade'];
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'] ?? null;

        if (!$currentPackage || !$targetPackageName) {
            return ['success' => false, 'error' => 'Current or target package missing for downgrade'];
        }

        try {
            $targetPackage = Package::where('name', $targetPackageName)->first();
            if (!$targetPackage) {
                return ['success' => false, 'error' => 'Target package not found'];
            }

            $newProductId = $this->getProductIdForPackage($targetPackage);
            if (!$newProductId) {
                return ['success' => false, 'error' => 'Product ID not found for target package'];
            }

            $expiresAt = $activeLicense->expires_at;
            $isExpired = $expiresAt && $expiresAt->isPast();

            if ($isExpired) {
                $effectiveDate = now()->toDateTimeString();
                $appliesAtPeriodEnd = false;
            } elseif ($expiresAt) {
                $effectiveDate = $expiresAt->toDateTimeString();
                $appliesAtPeriodEnd = true;
            } elseif ($activeLicense->activated_at) {
                try {
                    $effectiveDate = $activeLicense->activated_at->copy()->addMonth()->toDateTimeString();
                } catch (\Throwable $e) {
                    $effectiveDate = now()->addMonth()->toDateTimeString();
                }
                $appliesAtPeriodEnd = true;
            } else {
                $effectiveDate = now()->addMonth()->toDateTimeString();
                $appliesAtPeriodEnd = true;
            }

            // Create scheduled downgrade order with amount = 0
            // Payment will be processed automatically by the gateway when the downgrade becomes active
            // at the end of the current billing period (scheduled_activation_date)
            $order = Order::create([
                'user_id' => $this->user->id,
                'package_id' => $targetPackage->id,
                'amount' => 0, // No immediate payment - will be charged when downgrade becomes active
                'currency' => 'USD',
                'status' => 'scheduled_downgrade',
                'order_type' => 'downgrade',
                'transaction_id' => 'PPG-DOWNGRADE-' . Str::random(10),
                'metadata' => [
                    'subscription_id' => $activeLicense->subscription_id,
                    'original_package_name' => $currentPackage->name,
                    'original_package_price' => $currentPackage->price,
                    'target_package_name' => $targetPackageName,
                    'target_package_price' => $targetPackage->price, // Price to charge when downgrade becomes active
                    'product_id' => $newProductId,
                    'scheduled_activation_date' => $effectiveDate,
                    'scheduled_at' => now()->toISOString(),
                ],
            ]);

            $logMessage = $isExpired
                ? '[PayProGlobalPaymentGateway::handleDowngrade] Downgrade processed immediately for expired license'
                : '[PayProGlobalPaymentGateway::handleDowngrade] Downgrade scheduled successfully';

            Log::info($logMessage, [
                'user_id' => $this->user->id,
                'subscription_id' => $activeLicense->subscription_id,
                'order_id' => $order->id,
                'effective_date' => $effectiveDate,
                'is_expired' => $isExpired,
            ]);

            $message = $isExpired
                ? 'Downgrade processed successfully. Your subscription has been updated immediately.'
                : 'Downgrade scheduled successfully. It will take effect at the end of your current billing period.';

            return [
                'success' => true,
                'message' => $message,
                'current_package' => $currentPackage->name,
                'target_package' => $targetPackageName,
                'effective_date' => $effectiveDate,
                'applies_at_period_end' => $appliesAtPeriodEnd,
                'order_id' => $order->id,
            ];
        } catch (\Exception $e) {
            Log::error('[PayProGlobalPaymentGateway::handleDowngrade] Exception during downgrade', [
                'user_id' => $this->user->id,
                'subscription_id' => $activeLicense->subscription_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => 'An error occurred while scheduling the downgrade: ' . $e->getMessage()];
        }
    }

    private function changeSubscriptionProduct(array $paymentData, string $action, string $subscriptionId): array
    {
        $targetPackageName = $paymentData['package'] ?? null;
        if (!$targetPackageName) {
            return ['success' => false, 'error' => "Target package missing for {$action}"];
        }

        $targetPackage = Package::where('name', $targetPackageName)->first();
        if (!$targetPackage) {
            return ['success' => false, 'error' => 'Target package not found'];
        }

        try {
            $newProductId = $this->getProductIdForPackage($targetPackage);
            if (!$newProductId) {
                return ['success' => false, 'error' => 'Product ID not found for target package'];
            }

            $subscriptionIdInt = (int) $subscriptionId;
            if (!$subscriptionIdInt) {
                return ['success' => false, 'error' => 'Invalid subscription ID format'];
            }

            $result = $this->changeProduct($subscriptionIdInt, (int) $newProductId);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => "Subscription {$action}d successfully",
                    'subscription_id' => $subscriptionId,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("[PayProGlobalPaymentGateway::changeSubscriptionProduct] Exception during {$action}", [
                'user_id' => $this->user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => "An error occurred while {$action}ing the subscription: {$e->getMessage()}"];
        }
    }

    private function changeProduct(int $subscriptionId, int $productId, int $quantity = 1, bool $sendNotification = true): array
    {
        try {
            $apiEndpoint = $this->testMode
                ? ($this->endpoints['api']['sandbox'] ?? 'https://sandbox.payproglobal.com/api')
                : ($this->endpoints['api']['live'] ?? 'https://store.payproglobal.com/api');

            $url = rtrim($apiEndpoint, '/') . '/Subscriptions/ChangeProduct';

            $payload = [
                'productId' => $productId,
                'quantity' => $quantity,
                'sendCustomerNotification' => $sendNotification,
                'subscriptionId' => $subscriptionId,
                'vendorAccountId' => (int) $this->vendorAccountId,
                'apiSecretKey' => $this->apiSecretKey,
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['isSuccess'] ?? false) {
                    Log::info('[PayProGlobalPaymentGateway::changeProduct] Product changed successfully', [
                        'subscription_id' => $subscriptionId,
                        'product_id' => $productId,
                    ]);

                    return ['success' => true, 'data' => $responseData];
                }

                $errors = $responseData['errors'] ?? [];
                $errorMessages = [];
                foreach ($errors as $error) {
                    $property = $error['propertyWithError'] ?? 'unknown';
                    $messages = $error['propertyErrorMessages'] ?? [];
                    $errorMessages[] = "{$property}: " . implode(', ', $messages);
                }

                $errorMessage = !empty($errorMessages)
                    ? implode('; ', $errorMessages)
                    : 'Product change failed';

                Log::error('[PayProGlobalPaymentGateway::changeProduct] Product change failed', [
                    'subscription_id' => $subscriptionId,
                    'product_id' => $productId,
                    'errors' => $errors,
                ]);

                return ['success' => false, 'error' => $errorMessage];
            }

            $errorMessage = $this->extractErrorMessage($response);
            Log::error('[PayProGlobalPaymentGateway::changeProduct] API error', [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return ['success' => false, 'error' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('[PayProGlobalPaymentGateway::changeProduct] Exception', [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'An error occurred while changing the product: ' . $e->getMessage()];
        }
    }

    private function getProductIdForPackage(Package $package): ?int
    {
        $productId = $package->getGatewayProductId('PayProGlobal');
        if ($productId) {
            return (int) $productId;
        }

        $packageKey = strtolower(str_replace([' ', '-'], '', $package->name));
        if (isset($this->productIds[$packageKey])) {
            return (int) $this->productIds[$packageKey];
        }

        return null;
    }

    private function extractErrorMessage($response): string
    {
        try {
            $errorData = $response->json();
            if (isset($errorData['errors']) && is_array($errorData['errors'])) {
                $messages = [];
                foreach ($errorData['errors'] as $error) {
                    $property = $error['propertyWithError'] ?? 'unknown';
                    $errorMessages = $error['propertyErrorMessages'] ?? [];
                    $messages[] = "{$property}: " . implode(', ', $errorMessages);
                }
                return !empty($messages) ? implode('; ', $messages) : 'Unknown error';
            }
            return $errorData['message'] ?? $errorData['error'] ?? 'Unknown error';
        } catch (\Exception $e) {
            return $response->body() ?: 'Unknown error';
        }
    }

    public function handleCancellation(User $user, ?string $subscriptionId = null): array
    {
        $activeLicense = $user->userLicence;

        if (!$activeLicense || !$activeLicense->subscription_id) {
            Log::error('[PayProGlobalPaymentGateway::handleCancellation] No subscription ID found', [
                'user_id' => $user->id,
                'has_license' => $activeLicense !== null,
            ]);

            return [
                'success' => false,
                'message' => 'No active subscription found to cancel'
            ];
        }

        if (!$subscriptionId) {
            $subscriptionId = $activeLicense->subscription_id;
        }

        try {
            $reasonText = 'Customer requested cancellation';

            $expiresAt = $activeLicense->expires_at;
            if ($expiresAt && $expiresAt->isPast()) {
                return [
                    'success' => false,
                    'message' => 'Active license already expired, cannot schedule cancellation'
                ];
            }

            if ($expiresAt) {
                $effectiveDate = $expiresAt->toDateTimeString();
            } elseif ($activeLicense->activated_at) {
                try {
                    $effectiveDate = $activeLicense->activated_at->copy()->addMonth()->toDateTimeString();
                } catch (\Throwable $e) {
                    $effectiveDate = now()->addMonth()->toDateTimeString();
                }
            } else {
                $effectiveDate = now()->addMonth()->toDateTimeString();
            }

            $activeLicense->update([
'status' => 'cancelled_at_period_end'
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $user->package_id,
                'amount' => 0,
                'currency' => 'USD',
                'status' => 'cancelled',
                'order_type' => 'cancellation',
                'transaction_id' => 'PPG-CANCEL-' . Str::random(10),
                'metadata' => [
                    'subscription_id' => $subscriptionId,
                    'reason_text' => $reasonText,
                    'scheduled_activation_date' => $effectiveDate,
                    'scheduled_at' => now()->toISOString(),
                    'cancelled_at' => now()->toISOString(),
                    'cancellation_scheduled' => true,
                ]
            ]);

            Log::info('[PayProGlobalPaymentGateway::handleCancellation] Cancellation scheduled successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'order_id' => $order->id,
                'effective_date' => $effectiveDate,
            ]);

            return [
                'success' => true,
                'message' => 'Subscription cancellation scheduled successfully. Your subscription will remain active until the end of your current billing period.',
                'cancellation_type' => 'end_of_billing_period',
                'order_id' => $order->id,
                'subscription_id' => $subscriptionId,
                'effective_date' => $effectiveDate,
            ];
        } catch (\Exception $e) {
            Log::error('[PayProGlobalPaymentGateway::handleCancellation] Exception during cancellation', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while scheduling the cancellation: ' . $e->getMessage()
            ];
        }
    }
}
