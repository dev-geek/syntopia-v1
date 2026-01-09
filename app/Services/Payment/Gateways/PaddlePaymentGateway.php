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
    TenantAssignmentService,
    Payment\PackageGatewayService,
};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaddlePaymentGateway implements PaymentGatewayInterface
{
    private string $storefront;
    private string $apiKey;
    private string $apiBaseUrl;
    private string $webhookSecret;
    private ?User $user = null;
    private ?Order $order = null;
    private array $apiHeaders;

    public function __construct(
        private LicenseApiService $licenseApiService,
        private FirstPromoterService $firstPromoterService,
        private TenantAssignmentService $tenantAssignmentService,
        private PackageGatewayService $packageGatewayService,
    ) {
        $this->storefront = (string) config('payment.gateways.Paddle.checkout_url', 'https://sandbox-checkout.paddle.com');
        $this->apiKey = (string) config('payment.gateways.Paddle.api_key', '');
        $this->apiBaseUrl = rtrim((string) config('payment.gateways.Paddle.api_url', 'https://api.paddle.com'), '/');
        $this->webhookSecret = (string) config('payment.gateways.Paddle.webhook_secret', '');
        $this->apiHeaders = ['accept' => 'application/json', 'content-type' => 'application/json'];
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

    public function createCheckout(array $paymentData, bool $returnRedirect = true): array
    {
        $isUpgrade = (bool) ($paymentData['is_upgrade'] ?? false);

        if (!$paymentData['user']->tenant_id) {
            $assignmentResult = $this->tenantAssignmentService->assignTenantWithRetry($paymentData['user']);

            if (!($assignmentResult['success'] ?? false) || empty($assignmentResult['data']['tenantId'] ?? null)) {
                Log::error('[PaddlePaymentGateway::createCheckout] Failed to assign tenant before checkout', [
                    'user_id' => $paymentData['user']->id,
                    'result'  => $assignmentResult,
                ]);

                return [
                    'success' => false,
                    'error'   => 'Account is not fully initialized (missing tenant). Please contact support.',
                ];
            }

            $paymentData['user']->refresh();
        }

        $licensePlan = $this->licenseApiService->resolvePlanLicense($paymentData['user']->tenant_id, $paymentData['package']);

        if (!$licensePlan) {
            Log::warning('[PaddlePaymentGateway::createCheckout] No licenses available for requested plan', [
                'user_id'      => $paymentData['user']->id,
                'tenant_id'    => $paymentData['user']->tenant_id,
                'package_name' => $paymentData['package'],
                'is_upgrade'   => $isUpgrade,
            ]);

            return [
                'success' => false,
                'error'   => 'Licenses temporarily unavailable for the selected plan. Please try again later or contact support.',
            ];
        }

        $package = $this->findPackageByName($paymentData['package']);
        if (!$package) {
            return ['success' => false, 'error' => 'Package not found'];
        }

        $priceId = $this->packageGatewayService->getPaddlePriceId($package, $this);
        if (!$priceId) {
            return ['success' => false, 'error' => 'Price ID not found for package'];
        }

        $secureHash = hash_hmac('sha256', $paymentData['user']->id . $paymentData['package'] . time(), $this->webhookSecret);

        $customData = [
            'user_id' => $paymentData['user']->id,
            'package' => $paymentData['package'],
            'package_id' => $package->id,
            'secure_hash' => $secureHash,
            'action' => $isUpgrade ? 'upgrade' : 'new'
        ];

        $transactionData = [
            'items' => [['price_id' => $priceId, 'quantity' => 1]],
            'customer_id' => $this->user->paddle_customer_id,
            'currency_code' => 'USD',
            'collection_mode' => 'automatic',
            'custom_data' => $customData,
            'checkout' => [
                'settings' => ['display_mode' => 'overlay'],
                'success_url' => route('payments.success', ['gateway' => 'paddle', 'transaction_id' => '{transaction_id}']),
                'cancel_url' => route('payments.popup-cancel')
            ]
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders($this->apiHeaders)
                ->post("{$this->apiBaseUrl}/transactions", $transactionData);

            if ($response && $response->successful()) {
                $transaction = $response->json()['data'] ?? [];
                $checkoutUrl = $transaction['checkout']['url'] ?? null;

                if (!$checkoutUrl) {
                    Log::error('[PaddlePaymentGateway::createCheckout] No checkout URL in transaction response', [
                        'transaction_id' => $transaction['id'] ?? null,
                    ]);
                    return ['success' => false, 'error' => 'Failed to get checkout URL from Paddle'];
                }

                if ($this->order && isset($transaction['id'])) {
                    $currentPackage = $this->user->package;
                    $metadata = array_merge($this->order->metadata ?? [], [
                        'source' => 'paddle_checkout_creation',
                        'action' => $isUpgrade ? 'upgrade' : 'new',
                        'transaction_id' => $transaction['id'],
                        'transaction_status' => $transaction['status'] ?? null,
                        'checkout_url' => $checkoutUrl,
                        'created_at' => now()->toISOString(),
                        'current_subscription' => [
                            'package_id' => $currentPackage?->id,
                            'package_name' => $currentPackage?->name,
                            'package_price' => $currentPackage?->price ?? 0,
                            'subscription_id' => $this->user->subscription_id,
                        ],
                        'target_package' => [
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                            'package_price' => $package->price,
                        ],
                    ]);

                    $this->order->update([
                        'transaction_id' => $transaction['id'],
                        'metadata' => $metadata,
                    ]);
                }

                return [
                    'success' => true,
                    'checkout_url' => $checkoutUrl,
                    'transaction_id' => $transaction['id'] ?? $this->order?->transaction_id,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create Paddle transaction: ' . $this->extractErrorMessage($response)
            ];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::createCheckout] Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create transaction: ' . $e->getMessage()];
        }
    }

    public function handleUpgrade(array $paymentData, bool $returnRedirect = true): array
    {
        if (!$this->user) {
            return ['success' => false, 'error' => 'User context not set for upgrade'];
        }

        $subscriptionId = $this->user->subscription_id;
        if (!$subscriptionId) {
            Log::warning('[PaddlePaymentGateway::handleUpgrade] No subscription ID found', ['user_id' => $this->user->id]);
            $paymentData['is_upgrade'] = true;
            return $this->createCheckout($paymentData, $returnRedirect);
        }

        return $this->changeSubscriptionPlan($paymentData, 'upgrade', $subscriptionId);
    }

    public function handleDowngrade(array $paymentData, bool $returnRedirect = true): array
    {
        if (!$this->user) {
            return ['success' => false, 'error' => 'User context not set for downgrade'];
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'] ?? null;

        if (!$currentPackage || !$targetPackageName) {
            return ['success' => false, 'error' => 'Current or target package missing for downgrade'];
        }

        $targetPackage = $this->findPackageByName($targetPackageName);
        if (!$targetPackage) {
            return ['success' => false, 'error' => 'Target package not found'];
        }

        $activeLicense = $this->user->userLicence;
        if (!$activeLicense) {
            return ['success' => false, 'error' => 'No active license found to schedule a downgrade'];
        }

        try {
            [$effectiveDate, $appliesAtPeriodEnd] = $this->calculateEffectiveDate($activeLicense);

            $orderData = [
                'user_id' => $this->user->id,
                'package_id' => $targetPackage->id,
                'amount' => 0,
                'currency' => 'USD',
                'status' => 'scheduled_downgrade',
                'order_type' => 'downgrade',
                'payment_gateway_id' => $this->order?->payment_gateway_id ?? $this->user->payment_gateway_id,
                'transaction_id' => null,
                'metadata' => [
                    'source' => 'paddle_downgrade_scheduling',
                    'subscription_id' => $activeLicense->subscription_id,
                    'scheduled_activation_date' => $effectiveDate->toDateTimeString(),
                    'scheduled_at' => now()->toISOString(),
                    'applies_at_period_end' => $appliesAtPeriodEnd,
                    'original_package_name' => $currentPackage->name,
                    'original_package_price' => $currentPackage->price,
                    'target_package_name' => $targetPackageName,
                    'target_package_price' => $targetPackage->price,
                    'current_subscription' => [
                        'package_id' => $currentPackage->id,
                        'package_name' => $currentPackage->name,
                        'package_price' => $currentPackage->price,
                        'subscription_id' => $activeLicense->subscription_id,
                    ],
                    'target_package' => [
                        'package_id' => $targetPackage->id,
                        'package_name' => $targetPackage->name,
                        'package_price' => $targetPackage->price,
                    ],
                ],
            ];

            $order = $this->createOrUpdateOrder($orderData, 'downgrade', ['pending', 'scheduled_downgrade'], true);

            return [
                'success' => true,
                'message' => $appliesAtPeriodEnd
                    ? 'Downgrade scheduled successfully. It will take effect at the end of your current billing period.'
                    : 'Downgrade processed successfully. Your subscription has been updated immediately.',
                'current_package' => $currentPackage->name,
                'target_package' => $targetPackageName,
                'effective_date' => $effectiveDate->toDateTimeString(),
                'applies_at_period_end' => $appliesAtPeriodEnd,
                'order_id' => $order->id,
            ];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::handleDowngrade] Exception', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'An error occurred while scheduling the downgrade: ' . $e->getMessage()];
        }
    }

    private function changeSubscriptionPlan(array $paymentData, string $action, string $subscriptionId): array
    {
        $targetPackageName = $paymentData['package'] ?? null;
        if (!$targetPackageName) {
            return ['success' => false, 'error' => "Target package missing for {$action}"];
        }

        $targetPackage = $this->findPackageByName($targetPackageName);
        if (!$targetPackage) {
            return ['success' => false, 'error' => 'Target package not found'];
        }

        try {
            $latestOrder = $this->getLatestTransactionOrder($this->user, $this->order?->payment_gateway_id);

            if (!$latestOrder?->transaction_id) {
                Log::warning("[PaddlePaymentGateway::changeSubscriptionPlan] No transaction ID found for {$action}", [
                    'user_id' => $this->user->id,
                ]);
                $paymentData['is_upgrade'] = ($action === 'upgrade');
                return $this->createCheckout($paymentData, true);
            }

            $newPriceId = $this->getPriceIdForPackage($targetPackage);
            if (!$newPriceId) {
                return ['success' => false, 'error' => 'Price ID not found for target package'];
            }

            $currentPackage = $this->user->package;
            $result = $this->updateTransaction(
                $latestOrder->transaction_id,
                [['price_id' => $newPriceId, 'quantity' => 1]],
                $action,
                $targetPackage->id
            );

            if ($result['success']) {
                // Create or update order with metadata for upgrade/downgrade
                $orderData = [
                    'user_id' => $this->user->id,
                    'package_id' => $targetPackage->id,
                    'amount' => $targetPackage->price,
                    'currency' => 'USD',
                    'status' => 'pending',
                    'order_type' => $action,
                    'payment_gateway_id' => $this->order?->payment_gateway_id ?? $this->user->payment_gateway_id,
                    'transaction_id' => $latestOrder->transaction_id,
                    'metadata' => [
                        'source' => 'paddle_subscription_plan_change',
                        'action' => $action,
                        'subscription_id' => $subscriptionId,
                        'transaction_id' => $latestOrder->transaction_id,
                        'checkout_url' => $result['checkout_url'] ?? null,
                        'scheduled_at' => now()->toISOString(),
                        'original_package_name' => $currentPackage?->name,
                        'original_package_price' => $currentPackage?->price ?? 0,
                        'target_package_name' => $targetPackage->name,
                        'target_package_price' => $targetPackage->price,
                        'current_subscription' => [
                            'package_id' => $currentPackage?->id,
                            'package_name' => $currentPackage?->name,
                            'package_price' => $currentPackage?->price ?? 0,
                            'subscription_id' => $subscriptionId,
                        ],
                        'target_package' => [
                            'package_id' => $targetPackage->id,
                            'package_name' => $targetPackage->name,
                            'package_price' => $targetPackage->price,
                        ],
                    ],
                ];

                $order = $this->createOrUpdateOrder($orderData, $action, ['pending'], false);

                return [
                    'success' => true,
                    'message' => "Package {$action}d successfully",
                    'transaction_id' => $latestOrder->transaction_id,
                    'checkout_url' => $result['checkout_url'] ?? null,
                    'order_id' => $order->id,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("[PaddlePaymentGateway::changeSubscriptionPlan] Exception during {$action}", [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => "An error occurred while {$action}ing the package: {$e->getMessage()}"];
        }
    }

    public function handleCancellation(User $user, ?string $subscriptionId = null, bool $cancelImmediately = false): array
    {
        $activeLicense = $user->userLicence;

        if (!$activeLicense) {
            Log::error('[PaddlePaymentGateway::handleCancellation] No active license found', ['user_id' => $user->id]);
            return ['success' => false, 'message' => 'No active subscription found to cancel'];
        }

        $transactionDetails = null;
        $transactionIdToFetch = null;

        // Check if subscriptionId is actually a transaction ID (starts with txn_)
        if ($subscriptionId && preg_match('/^txn_[a-z\d]+$/', $subscriptionId)) {
            $transactionIdToFetch = $subscriptionId;
            $subscriptionId = null; // Reset to fetch from transaction
        }

        if (!$subscriptionId) {
            // If we have a transaction ID, use it; otherwise get from latest order
            if ($transactionIdToFetch) {
                $transactionId = $transactionIdToFetch;
            } else {
                $latestOrder = $this->getLatestTransactionOrder($user, $user->payment_gateway_id);

                if (!$latestOrder?->transaction_id) {
                    Log::error('[PaddlePaymentGateway::handleCancellation] No transaction ID found', ['user_id' => $user->id]);
                    return ['success' => false, 'message' => 'No active transaction found to cancel subscription'];
                }

                $transactionId = $latestOrder->transaction_id;
            }

            try {
                $transaction = $this->getTransaction($transactionId);
                if (!$transaction) {
                    return ['success' => false, 'message' => 'Failed to retrieve transaction details'];
                }

                $subscriptionId = $transaction['subscription_id'] ?? null;
                if (!$subscriptionId) {
                    Log::error('[PaddlePaymentGateway::handleCancellation] No subscription_id in transaction', [
                        'user_id' => $user->id,
                        'transaction_id' => $transactionId,
                    ]);
                    return ['success' => false, 'message' => 'No subscription found in transaction. Cannot cancel subscription.'];
                }

                $transactionDetails = [
                    'transaction_id' => $transactionId,
                    'transaction_status' => $transaction['status'] ?? null,
                    'invoice_id' => $transaction['invoice_id'] ?? null,
                    'invoice_number' => $transaction['invoice_number'] ?? null,
                    'billing_period' => $transaction['billing_period'] ?? null,
                    'currency_code' => $transaction['currency_code'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('[PaddlePaymentGateway::handleCancellation] Exception getting transaction', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => 'An error occurred while retrieving transaction: ' . $e->getMessage()];
            }
        }

        if (!$subscriptionId || !preg_match('/^sub_[a-z\d]{26}$/', $subscriptionId)) {
            Log::error('[PaddlePaymentGateway::handleCancellation] Invalid subscription ID', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
            ]);
            return ['success' => false, 'message' => 'Invalid subscription ID format. Cannot cancel subscription.'];
        }

        try {
            // First, check the current subscription status
            $subscriptionResponse = $this->makeApiRequest('get', "{$this->apiBaseUrl}/subscriptions/{$subscriptionId}");
            
            if ($subscriptionResponse && $subscriptionResponse->successful()) {
                $subscriptionData = $subscriptionResponse->json()['data'] ?? [];
                $subscriptionStatus = $subscriptionData['status'] ?? null;
                $scheduledChange = $subscriptionData['scheduled_change'] ?? null;
                
                // If subscription is already cancelled or has a scheduled cancellation, handle it
                if ($subscriptionStatus === 'canceled' || ($scheduledChange && isset($scheduledChange['action']) && $scheduledChange['action'] === 'cancel')) {
                    Log::info('[PaddlePaymentGateway::handleCancellation] Subscription already has cancellation scheduled', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                        'status' => $subscriptionStatus,
                        'scheduled_change' => $scheduledChange,
                    ]);
                    
                    // Update license and create order to reflect the existing cancellation
                    $canceledAt = $subscriptionData['canceled_at'] ?? null;
                    $effectiveAt = $scheduledChange['effective_at'] ?? $canceledAt;
                    
                    $activeLicense->update([
                        'status' => 'cancelled_at_period_end',
                        'cancelled_at' => $canceledAt ? \Carbon\Carbon::parse($canceledAt) : now(),
                    ]);
                    
                    $currentPackage = $user->package;
                    $metadata = [
                        'source' => 'paddle_cancellation_scheduling',
                        'subscription_id' => $subscriptionId,
                        'subscription_status' => $subscriptionStatus,
                        'cancelled_at' => $canceledAt,
                        'scheduled_change' => $scheduledChange,
                        'effective_at' => $effectiveAt,
                        'next_billed_at' => $subscriptionData['next_billed_at'] ?? null,
                        'cancelled_at_timestamp' => now()->toISOString(),
                        'cancellation_scheduled' => true,
                        'already_scheduled' => true,
                        'current_subscription' => [
                            'package_id' => $currentPackage?->id,
                            'package_name' => $currentPackage?->name,
                            'package_price' => $currentPackage?->price ?? 0,
                            'subscription_id' => $subscriptionId,
                        ],
                    ];
                    
                    $orderData = [
                        'user_id' => $user->id,
                        'package_id' => $user->package_id,
                        'amount' => 0,
                        'currency' => 'USD',
                        'status' => 'cancelled',
                        'order_type' => 'cancellation',
                        'payment_gateway_id' => $user->payment_gateway_id,
                        'transaction_id' => 'PADDLE-CANCEL-' . Str::random(10),
                        'metadata' => $metadata,
                    ];
                    
                    $order = $this->createOrUpdateOrder($orderData, 'cancellation', ['pending', 'cancelled'], true);
                    
                    return [
                        'success' => true,
                        'message' => 'Subscription cancellation is already scheduled. Your subscription will remain active until the end of your current billing period.',
                        'cancellation_type' => 'end_of_billing_period',
                        'order_id' => $order->id,
                        'subscription_id' => $subscriptionId,
                        'effective_at' => $effectiveAt,
                        'next_billed_at' => $subscriptionData['next_billed_at'] ?? null,
                    ];
                }
            }
            
            $response = $this->makeApiRequest('post', "{$this->apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
                'effective_from' => 'next_billing_period',
            ]);

            if (!$response || !$response->successful()) {
                $errorMessage = $this->extractErrorMessage($response);
                
                // Check if the error is because subscription is already cancelled
                $responseBody = $response->json() ?? [];
                $errorCode = $responseBody['error']?['type'] ?? '';
                
                if (stripos($errorMessage, 'already canceled') !== false || 
                    stripos($errorMessage, 'already cancelled') !== false ||
                    $errorCode === 'subscription_already_canceled') {
                    
                    Log::info('[PaddlePaymentGateway::handleCancellation] Subscription already cancelled, creating order record', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                    ]);
                    
                    // Get subscription details to create order
                    $subscriptionResponse = $this->makeApiRequest('get', "{$this->apiBaseUrl}/subscriptions/{$subscriptionId}");
                    $subscriptionData = $subscriptionResponse && $subscriptionResponse->successful() 
                        ? ($subscriptionResponse->json()['data'] ?? []) 
                        : [];
                    
                    $canceledAt = $subscriptionData['canceled_at'] ?? now()->toISOString();
                    $scheduledChange = $subscriptionData['scheduled_change'] ?? null;
                    $effectiveAt = $scheduledChange['effective_at'] ?? $canceledAt;
                    
                    $activeLicense->update([
                        'status' => 'cancelled_at_period_end',
                        'cancelled_at' => \Carbon\Carbon::parse($canceledAt),
                    ]);
                    
                    $currentPackage = $user->package;
                    $metadata = [
                        'source' => 'paddle_cancellation_scheduling',
                        'subscription_id' => $subscriptionId,
                        'subscription_status' => $subscriptionData['status'] ?? 'canceled',
                        'cancelled_at' => $canceledAt,
                        'scheduled_change' => $scheduledChange,
                        'effective_at' => $effectiveAt,
                        'next_billed_at' => $subscriptionData['next_billed_at'] ?? null,
                        'cancelled_at_timestamp' => now()->toISOString(),
                        'cancellation_scheduled' => true,
                        'already_cancelled' => true,
                        'current_subscription' => [
                            'package_id' => $currentPackage?->id,
                            'package_name' => $currentPackage?->name,
                            'package_price' => $currentPackage?->price ?? 0,
                            'subscription_id' => $subscriptionId,
                        ],
                    ];
                    
                    $orderData = [
                        'user_id' => $user->id,
                        'package_id' => $user->package_id,
                        'amount' => 0,
                        'currency' => 'USD',
                        'status' => 'cancelled',
                        'order_type' => 'cancellation',
                        'payment_gateway_id' => $user->payment_gateway_id,
                        'transaction_id' => 'PADDLE-CANCEL-' . Str::random(10),
                        'metadata' => $metadata,
                    ];
                    
                    $order = $this->createOrUpdateOrder($orderData, 'cancellation', ['pending', 'cancelled'], true);
                    
                    return [
                        'success' => true,
                        'message' => 'Subscription cancellation is already scheduled. Your subscription will remain active until the end of your current billing period.',
                        'cancellation_type' => 'end_of_billing_period',
                        'order_id' => $order->id,
                        'subscription_id' => $subscriptionId,
                        'effective_at' => $effectiveAt,
                        'next_billed_at' => $subscriptionData['next_billed_at'] ?? null,
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to cancel subscription: ' . $errorMessage
                ];
            }

            $updatedSubscription = $response->json()['data'] ?? [];
            $scheduledChange = $updatedSubscription['scheduled_change'] ?? null;
            $canceledAt = $updatedSubscription['canceled_at'] ?? null;
            $effectiveAt = $scheduledChange['effective_at'] ?? $canceledAt;

            $activeLicense->update([
                'status' => 'cancelled_at_period_end',
                'cancelled_at' => $canceledAt ? \Carbon\Carbon::parse($canceledAt) : now(),
            ]);

            $currentPackage = $user->package;
            $metadata = [
                'source' => 'paddle_cancellation_scheduling',
                'subscription_id' => $subscriptionId,
                'subscription_status' => $updatedSubscription['status'] ?? null,
                'cancelled_at' => $canceledAt,
                'scheduled_change' => $scheduledChange,
                'effective_at' => $effectiveAt,
                'next_billed_at' => $updatedSubscription['next_billed_at'] ?? null,
                'cancelled_at_timestamp' => now()->toISOString(),
                'cancellation_scheduled' => true,
                'current_subscription' => [
                    'package_id' => $currentPackage?->id,
                    'package_name' => $currentPackage?->name,
                    'package_price' => $currentPackage?->price ?? 0,
                    'subscription_id' => $subscriptionId,
                ],
            ];

            if ($transactionDetails) {
                $metadata['transaction_details'] = $transactionDetails;
            }

            $orderData = [
                'user_id' => $user->id,
                'package_id' => $user->package_id,
                'amount' => 0,
                'currency' => 'USD',
                'status' => 'cancelled',
                'order_type' => 'cancellation',
                'payment_gateway_id' => $user->payment_gateway_id,
                'transaction_id' => 'PADDLE-CANCEL-' . Str::random(10),
                'metadata' => $metadata,
            ];

            $order = $this->createOrUpdateOrder($orderData, 'cancellation', ['pending', 'cancelled'], true);

            return [
                'success' => true,
                'message' => 'Subscription cancellation scheduled successfully. Your subscription will remain active until the end of your current billing period.',
                'cancellation_type' => 'end_of_billing_period',
                'order_id' => $order->id,
                'subscription_id' => $subscriptionId,
                'effective_at' => $effectiveAt,
                'next_billed_at' => $updatedSubscription['next_billed_at'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::handleCancellation] Exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'An error occurred while scheduling the cancellation: ' . $e->getMessage()];
        }
    }

    private function updateTransaction(string $transactionId, array $items, string $action = 'upgrade', ?int $packageId = null): array
    {
        try {
            if (!$packageId) {
                $packageId = $this->order?->package_id
                    ?? $this->getLatestTransactionOrder($this->user, null, $transactionId)?->package_id;
            }

            $updateData = [
                'collection_mode' => 'automatic',
                'items' => $items,
                'customer_id' => $this->user->paddle_customer_id,
                'currency_code' => $this->order?->currency ?? 'USD',
                'custom_data' => [
                    'customer_reference_id' => (string)$this->user->id,
                    'package_id' => $packageId,
                    'action' => $action
                ],
                'billing_details' => [
                    'enable_checkout' => false,
                    'purchase_order_number' => null,
                    'additional_information' => null,
                    'payment_terms' => ['interval' => 'day', 'frequency' => 0]
                ],
                'checkout' => ['url' => null]
            ];

            $response = $this->makeApiRequest('patch', "{$this->apiBaseUrl}/transactions/{$transactionId}", $updateData);

            if ($response && $response->successful()) {
                $checkoutUrl = $response->json()['data']['checkout']['url'] ?? null;
                return ['success' => true, 'data' => $response->json(), 'checkout_url' => $checkoutUrl];
            }

            return ['success' => false, 'error' => $this->extractErrorMessage($response)];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::updateTransaction] Exception', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'An error occurred while updating the transaction: ' . $e->getMessage()];
        }
    }

    private function getPriceIdForPackage(Package $package): ?string
    {
        return $this->packageGatewayService->getPaddlePriceId($package, $this)
            ?? $this->getPriceIdFromConfig($package);
    }

    private function getPriceIdFromConfig(Package $package): ?string
    {
        $packageKey = strtolower(str_replace([' ', '-'], '', $package->name));
        $productIds = config('payment.gateways.Paddle.product_ids', []);

        if (!isset($productIds[$packageKey])) {
            return null;
        }

        $price = $this->findActivePriceForProduct((string) $productIds[$packageKey]);
        return $price['id'] ?? null;
    }

    private function extractErrorMessage($response): string
    {
        if (!$response) {
            return 'Unknown error';
        }

        try {
            $errorData = $response->json();
            return $errorData['error']['detail']
                ?? $errorData['error']['message']
                ?? $errorData['message']
                ?? $errorData['error']
                ?? 'Unknown error';
        } catch (\Exception $e) {
            return $response->body() ?: 'Unknown error';
        }
    }

    public function findProductByName(string $packageName): ?array
    {
        $packageKey = strtolower(str_replace([' ', '-'], '', $packageName));
        $productIds = config('payment.gateways.Paddle.product_ids', []);

        if (!isset($productIds[$packageKey]) || empty($productIds[$packageKey]) || $productIds[$packageKey] === 0) {
            return null;
        }

        try {
            $response = $this->makeApiRequest('get', "{$this->apiBaseUrl}/products/{$productIds[$packageKey]}");
            return $response && $response->successful() ? ($response->json()['data'] ?? null) : null;
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::findProductByName] Exception', [
                'package_name' => $packageName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function findActivePriceForProduct(string $productId): ?array
    {
        try {
            $response = $this->makeApiRequest('get', "{$this->apiBaseUrl}/prices", [
                'product_id' => $productId,
                'status' => 'active',
                'per_page' => 1,
            ]);

            if ($response && $response->successful()) {
                $prices = $response->json()['data'] ?? [];
                return $prices[0] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::findActivePriceForProduct] Exception', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function createOrGetCustomer(User $user): ?string
    {
        if ($user->paddle_customer_id) {
            return $user->paddle_customer_id;
        }

        try {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if (empty($name)) {
                $name = $user->email;
            }

            $response = $this->makeApiRequest('post', "{$this->apiBaseUrl}/customers", [
                'email' => $user->email,
                'name' => $name,
            ]);

            if ($response && $response->successful()) {
                $customerId = $response->json()['data']['id'] ?? null;

                if ($customerId) {
                    $user->update(['paddle_customer_id' => $customerId]);
                    return $customerId;
                }
            }

            Log::warning('[PaddlePaymentGateway::createOrGetCustomer] Failed to create Paddle customer', [
                'user_id' => $user->id,
                'status' => $response?->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::createOrGetCustomer] Exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function makeApiRequest(string $method, string $url, array $params = []): ?\Illuminate\Http\Client\Response
    {
        try {
            $request = Http::withToken($this->apiKey)->withHeaders(['accept' => 'application/json']);

            return match ($method) {
                'get' => $request->get($url, $params),
                'post', 'patch' => $request->withHeaders(['content-type' => 'application/json'])->{$method}($url, $params),
                'delete' => $request->delete($url),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::makeApiRequest] Exception', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getLatestTransactionOrder(User $user, ?int $paymentGatewayId = null, ?string $transactionId = null): ?Order
    {
        $query = $user->orders()
            ->where('status', 'completed')
            ->whereNotNull('transaction_id')
            ->where('transaction_id', 'like', 'txn_%');

        if ($paymentGatewayId) {
            $query->where('payment_gateway_id', $paymentGatewayId);
        }

        if ($transactionId) {
            $query->where('transaction_id', $transactionId);
        }

        return $query->latest('created_at')->first();
    }

    private function getTransaction(string $transactionId): ?array
    {
        $response = $this->makeApiRequest('get', "{$this->apiBaseUrl}/transactions/{$transactionId}");

        if (!$response || !$response->successful()) {
            Log::error('[PaddlePaymentGateway::getTransaction] Failed to get transaction', [
                'transaction_id' => $transactionId,
                'status' => $response?->status(),
                'error' => $this->extractErrorMessage($response),
            ]);
            return null;
        }

        return $response->json()['data'] ?? [];
    }

    private function findPackageByName(string $packageName): ?Package
    {
        return Package::where('name', $packageName)->first();
    }

    private function calculateEffectiveDate(UserLicence $activeLicense): array
    {
        $expiresAt = $activeLicense->expires_at;
        $isExpired = $expiresAt && $expiresAt->isPast();

        if ($isExpired) {
            return [now(), false];
        }

        if ($expiresAt) {
            return [$expiresAt, true];
        }

        if ($activeLicense->activated_at) {
            try {
                return [$activeLicense->activated_at->copy()->addMonth(), true];
            } catch (\Throwable $e) {
                return [now()->addMonth(), true];
            }
        }

        return [now()->addMonth(), true];
    }

    private function createOrUpdateOrder(array $orderData, string $orderType, array $allowedStatuses = ['pending'], bool $mergeMetadata = false): Order
    {
        // Check if we can reuse existing order
        if ($this->order
            && in_array($this->order->status, $allowedStatuses)
            && $this->order->order_type === $orderType
        ) {
            // Merge metadata
            if ($mergeMetadata && isset($orderData['metadata'])) {
                $orderData['metadata'] = array_merge($this->order->metadata ?? [], $orderData['metadata']);
            }

            $this->order->update($orderData);
            return $this->order;
        }

        // Create new order
        return Order::create($orderData);
    }

    /**
     * Cancel a scheduled cancellation in Paddle by updating the subscription
     * to remove the scheduled_change. This is called when user upgrades/downgrades
     * to override any existing scheduled cancellation.
     *
     * @param string $subscriptionId
     * @return bool
     */
    public function cancelScheduledCancellationInPaddle(string $subscriptionId): bool
    {
        if (!$subscriptionId || !preg_match('/^sub_[a-z\d]{26}$/', $subscriptionId)) {
            Log::warning('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] Invalid subscription ID', [
                'subscription_id' => $subscriptionId,
            ]);
            return false;
        }

        try {
            // First, check if there's a scheduled cancellation
            $subscriptionResponse = $this->makeApiRequest('get', "{$this->apiBaseUrl}/subscriptions/{$subscriptionId}");
            
            if (!$subscriptionResponse || !$subscriptionResponse->successful()) {
                Log::warning('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] Failed to get subscription', [
                    'subscription_id' => $subscriptionId,
                    'error' => $this->extractErrorMessage($subscriptionResponse),
                ]);
                return false;
            }

            $subscriptionData = $subscriptionResponse->json()['data'] ?? [];
            $scheduledChange = $subscriptionData['scheduled_change'] ?? null;
            
            // If there's no scheduled cancellation, nothing to do
            if (!$scheduledChange || !isset($scheduledChange['action']) || $scheduledChange['action'] !== 'cancel') {
                Log::debug('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] No scheduled cancellation found', [
                    'subscription_id' => $subscriptionId,
                ]);
                return true; // Not an error, just nothing to cancel
            }

            // Update subscription to remove scheduled_change by setting it to null
            // According to Paddle API, we can update subscription and remove scheduled_change
            $updateResponse = $this->makeApiRequest('patch', "{$this->apiBaseUrl}/subscriptions/{$subscriptionId}", [
                'scheduled_change' => null,
            ]);

            if ($updateResponse && $updateResponse->successful()) {
                Log::info('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] Successfully cancelled scheduled cancellation in Paddle', [
                    'subscription_id' => $subscriptionId,
                ]);
                return true;
            }

            Log::warning('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] Failed to cancel scheduled cancellation in Paddle', [
                'subscription_id' => $subscriptionId,
                'error' => $this->extractErrorMessage($updateResponse),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::cancelScheduledCancellationInPaddle] Exception', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
