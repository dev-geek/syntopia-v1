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

    public function __construct(
        private LicenseApiService $licenseApiService,
        private FirstPromoterService $firstPromoterService,
        private TenantAssignmentService $tenantAssignmentService,
    ) {
        $this->storefront = (string) config('payment.gateways.Paddle.checkout_url', 'https://sandbox-checkout.paddle.com');
        $this->apiKey = (string) config('payment.gateways.Paddle.api_key', '');
        $this->apiBaseUrl = (string) config('payment.gateways.Paddle.api_url', 'https://api.paddle.com');
        $this->webhookSecret = (string) config('payment.gateways.Paddle.webhook_secret', '');
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
        Log::info('[PaddlePaymentGateway::createCheckout] called', ['paymentData' => $paymentData, 'returnRedirect' => $returnRedirect]);

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

        $secureHash = hash_hmac(
            'sha256',
            $paymentData['user']->id . $paymentData['package'] . time(),
            $this->webhookSecret
        );

        $baseSuccessUrl = route('payments.success');

        $queryParams = [
            'referrer' => $paymentData['user']->id,
            'contactEmail' => $this->user->email,
            'contactFirstName' => $this->user->first_name ?? '',
            'contactLastName' => $paymentData['user']->last_name ?? '',
            'tags' => json_encode([
                'user_id'     => $paymentData['user']->id,
                'package'     => $paymentData['package'],
                'package_id'  => $this->order->package_id,
                'secure_hash' => $secureHash,
                'action'      => $isUpgrade ? 'upgrade' : 'new'
            ]),
            'mode' => 'popup',
            'successUrl' => $baseSuccessUrl . '?' . http_build_query([
                'gateway' => 'paddle',
                'success-url' => $baseSuccessUrl,
                'transaction_id' => '{orderReference}',
                'popup' => 'true',
                'package_name' => $paymentData['package'],
                'payment_gateway_id' => $this->order->payment_gateway_id,
            ]),
            'cancelUrl' => route('payments.popup-cancel', [
                'gateway' => 'paddle',
                'package_name' => $paymentData['package'],
                'payment_gateway_id' => $this->order->payment_gateway_id,
            ]),
        ];

        if ($paymentData['is_upgrade'] && $this->user->subscription_id) {
            $queryParams['subscription_id'] = $this->user->subscription_id;
        }

        $checkoutUrl = "https://{$this->storefront}/{$paymentData['package']}?" . http_build_query($queryParams);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
        ];
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

        $subscriptionId = $this->user->subscription_id;
        if (!$subscriptionId) {
            return ['success' => false, 'error' => 'No active subscription found to downgrade'];
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'] ?? null;

        if (!$currentPackage || !$targetPackageName) {
            return ['success' => false, 'error' => 'Current or target package missing for downgrade'];
        }

        $targetPackage = Package::where('name', $targetPackageName)->first();
        if (!$targetPackage) {
            return ['success' => false, 'error' => 'Target package not found'];
        }

        try {
            $subscription = $this->getSubscription($subscriptionId);
            if (!$subscription) {
                return ['success' => false, 'error' => 'Failed to retrieve current subscription'];
            }

            $activeLicense = $this->user->userLicence;
            if (!$activeLicense) {
                return ['success' => false, 'error' => 'No active license found to schedule a downgrade'];
            }

            $nextBilledAt = $subscription['data']['next_billed_at'] ?? null;
            $expiresAt = $activeLicense->expires_at;

            if ($expiresAt && $expiresAt->isPast()) {
                return ['success' => false, 'error' => 'Active license already expired, cannot schedule downgrade'];
            }

            if ($nextBilledAt) {
                $effectiveDate = \Carbon\Carbon::parse($nextBilledAt);
            } elseif ($expiresAt) {
                $effectiveDate = $expiresAt;
            } elseif ($activeLicense->activated_at) {
                try {
                    $effectiveDate = $activeLicense->activated_at->copy()->addMonth();
                } catch (\Throwable $e) {
                    $effectiveDate = now()->addMonth();
                }
            } else {
                $effectiveDate = now()->addMonth();
            }

            $order = Order::create([
                'user_id' => $this->user->id,
                'package_id' => $targetPackage->id,
                'amount' => 0,
                'currency' => 'USD',
                'status' => 'scheduled_downgrade',
                'order_type' => 'downgrade',
                'transaction_id' => 'PADDLE-DOWNGRADE-' . Str::random(10),
                'metadata' => [
                    'subscription_id' => $subscriptionId,
                    'original_package_name' => $currentPackage->name,
                    'target_package_name' => $targetPackageName,
                    'scheduled_activation_date' => $effectiveDate->toDateTimeString(),
                    'scheduled_at' => now()->toISOString(),
                ],
            ]);

            Log::info('[PaddlePaymentGateway::handleDowngrade] Downgrade scheduled successfully', [
                'user_id' => $this->user->id,
                'subscription_id' => $subscriptionId,
                'order_id' => $order->id,
                'effective_date' => $effectiveDate->toDateTimeString(),
            ]);

            return [
                'success' => true,
                'message' => 'Downgrade scheduled successfully. It will take effect at the end of your current billing period.',
                'current_package' => $currentPackage->name,
                'target_package' => $targetPackageName,
                'effective_date' => $effectiveDate->toDateTimeString(),
                'applies_at_period_end' => true,
                'order_id' => $order->id,
            ];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::handleDowngrade] Exception during downgrade', [
                'user_id' => $this->user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

        $targetPackage = Package::where('name', $targetPackageName)->first();
        if (!$targetPackage) {
            return ['success' => false, 'error' => 'Target package not found'];
        }

        try {
            $subscription = $this->getSubscription($subscriptionId);
            if (!$subscription) {
                return ['success' => false, 'error' => 'Failed to retrieve current subscription'];
            }

            $newPriceId = $this->getPriceIdForPackage($targetPackage);
            if (!$newPriceId) {
                return ['success' => false, 'error' => 'Price ID not found for target package'];
            }

            $currentPackage = $this->user->package;
            $currentPriceId = $currentPackage ? $this->getPriceIdForPackage($currentPackage) : null;
            $items = $this->buildSubscriptionItems($subscription['data']['items'] ?? [], $currentPriceId, $newPriceId);

            $result = $this->updateSubscription($subscriptionId, $items, 'prorated_immediately');

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => "Subscription {$action}d successfully",
                    'subscription_id' => $subscriptionId,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("[PaddlePaymentGateway::changeSubscriptionPlan] Exception during {$action}", [
                'user_id' => $this->user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => "An error occurred while {$action}ing the subscription: {$e->getMessage()}"];
        }
    }

    private function buildSubscriptionItems(array $existingItems, ?string $currentPriceId, string $newPriceId): array
    {
        $items = [];
        $basePlanReplaced = false;

        foreach ($existingItems as $item) {
            $priceId = $item['price']['id'] ?? null;
            if (!$priceId) {
                continue;
            }

            if ($currentPriceId && $priceId === $currentPriceId && !$basePlanReplaced) {
                $items[] = ['price_id' => $newPriceId, 'quantity' => 1];
                $basePlanReplaced = true;
            } else {
                $items[] = ['price_id' => $priceId, 'quantity' => $item['quantity'] ?? 1];
            }
        }

        if (!$basePlanReplaced) {
            $items = empty($items)
                ? [['price_id' => $newPriceId, 'quantity' => 1]]
                : [['price_id' => $newPriceId, 'quantity' => 1], ...array_slice($items, 1)];
        }

        return $items;
    }

    public function handleCancellation(User $user, ?string $subscriptionId = null, bool $cancelImmediately = false): array
    {
        if (!$subscriptionId) {
            $activeLicense = $user->userLicence;

            if (!$activeLicense || !$activeLicense->subscription_id) {
                Log::error('[PaddlePaymentGateway::cancelSubscription] No subscription ID found', [
                    'user_id' => $user->id,
                    'has_license' => $activeLicense !== null,
                ]);

                return [
                    'success' => false,
                    'message' => 'No active subscription found to cancel'
                ];
            }

            $subscriptionId = $activeLicense->subscription_id;
        }

        try {
            $subscription = $this->getSubscription($subscriptionId);
            if (!$subscription) {
                return [
                    'success' => false,
                    'message' => 'Failed to retrieve subscription details'
                ];
            }

            $subscriptionData = $subscription['data'] ?? [];
            $subscriptionStatus = $subscriptionData['status'] ?? null;

            if (!in_array($subscriptionStatus, ['active', 'paused'])) {
                return [
                    'success' => false,
                    'message' => "Cannot cancel subscription with status: {$subscriptionStatus}. Only active or paused subscriptions can be canceled."
                ];
            }

            if ($subscriptionStatus === 'past_due') {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel a subscription with past due transactions. Please resolve payment issues first.'
                ];
            }

            if ($cancelImmediately) {
                $unbilledCharges = $this->checkUnbilledCharges($subscriptionId);
                if ($unbilledCharges) {
                    return [
                        'success' => false,
                        'message' => 'Cannot cancel immediately. Subscription has unbilled charges that will be forgiven if canceled at period end.'
                    ];
                }
            }

            $effectiveFrom = $cancelImmediately ? 'immediately' : 'next_billing_period';
            $url = rtrim($this->apiBaseUrl, '/') . "/subscriptions/{$subscriptionId}/cancel";
            $response = $this->makeApiRequest('post', $url, [
                'effective_from' => $effectiveFrom,
            ]);

            if ($response && $response->successful()) {
                $responseData = $response->json();
                $updatedSubscription = $responseData['data'] ?? [];

                Log::info('[PaddlePaymentGateway::cancelSubscription] Subscription cancelled successfully', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscriptionId,
                    'effective_from' => $effectiveFrom,
                ]);

                $scheduledChange = $updatedSubscription['scheduled_change'] ?? null;
                $canceledAt = $updatedSubscription['canceled_at'] ?? null;
                $nextBilledAt = $updatedSubscription['next_billed_at'] ?? null;
                $effectiveAt = $scheduledChange['effective_at'] ?? $canceledAt;

                $activeLicense = $user->userLicence;
                if ($activeLicense) {
                    $activeLicense->update([
                        'status' => $cancelImmediately ? 'cancelled' : 'cancelled_at_period_end',
                        'cancelled_at' => $canceledAt ? \Carbon\Carbon::parse($canceledAt) : now(),
                    ]);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => $user->package_id,
                    'amount' => 0,
                    'currency' => 'USD',
                    'status' => $cancelImmediately ? 'cancelled' : 'cancellation_scheduled',
                    'transaction_id' => 'PADDLE-CANCEL-' . Str::random(10),
                    'metadata' => [
                        'subscription_id' => $subscriptionId,
                        'subscription_status' => $updatedSubscription['status'] ?? null,
                        'cancelled_at' => $canceledAt,
                        'scheduled_change' => $scheduledChange,
                        'effective_at' => $effectiveAt,
                        'next_billed_at' => $nextBilledAt,
                        'cancel_immediately' => $cancelImmediately,
                        'cancelled_at_timestamp' => now()->toISOString(),
                    ]
                ]);

                $message = $cancelImmediately
                    ? 'Subscription canceled successfully. Access has been revoked immediately.'
                    : 'Subscription cancellation scheduled successfully. Your subscription will remain active until the end of the current billing period.';

                return [
                    'success' => true,
                    'message' => $message,
                    'order_id' => $order->id,
                    'subscription_id' => $subscriptionId,
                    'canceled_at' => $canceledAt,
                    'effective_at' => $effectiveAt,
                    'next_billed_at' => $nextBilledAt,
                ];
            }

            $errorMessage = $this->extractErrorMessage($response);

            Log::error('[PaddlePaymentGateway::cancelSubscription] Paddle API error', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'status' => $response?->status(),
                'error' => $errorMessage,
                'response' => $response?->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::cancelSubscription] Exception during cancellation', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while cancelling the subscription: ' . $e->getMessage()
            ];
        }
    }

    private function checkUnbilledCharges(string $subscriptionId): bool
    {
        try {
            $url = rtrim($this->apiBaseUrl, '/') . "/subscriptions/{$subscriptionId}";
            $response = $this->makeApiRequest('get', $url, [
                'include' => 'next_transaction',
            ]);

            if ($response && $response->successful()) {
                $data = $response->json();
                $subscription = $data['data'] ?? [];
                $nextTransaction = $subscription['next_transaction'] ?? null;

                if ($nextTransaction) {
                    $items = $nextTransaction['items'] ?? [];
                    foreach ($items as $item) {
                        $price = $item['price'] ?? null;
                        if ($price && ($price['billing_cycle'] ?? null) === null) {
                            return true;
                        }
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('[PaddlePaymentGateway::checkUnbilledCharges] Failed to check unbilled charges', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getSubscription(string $subscriptionId): ?array
    {
        $url = rtrim($this->apiBaseUrl, '/') . "/subscriptions/{$subscriptionId}";
        $response = $this->makeApiRequest('get', $url);

        if ($response && $response->successful()) {
            return $response->json();
        }

        Log::error('[PaddlePaymentGateway::getSubscription] Failed to get subscription', [
            'subscription_id' => $subscriptionId,
            'status' => $response?->status(),
            'response' => $response?->body(),
        ]);

        return null;
    }

    private function updateSubscription(string $subscriptionId, array $items, string $prorationBillingMode = 'prorated_immediately'): array
    {
        try {
            $url = rtrim($this->apiBaseUrl, '/') . "/subscriptions/{$subscriptionId}";
            $response = $this->makeApiRequest('patch', $url, [
                'proration_billing_mode' => $prorationBillingMode,
                'items' => $items,
            ]);

            if ($response && $response->successful()) {
                $responseData = $response->json();
                Log::info('[PaddlePaymentGateway::updateSubscription] Subscription updated successfully', [
                    'subscription_id' => $subscriptionId,
                ]);

                return ['success' => true, 'data' => $responseData];
            }

            $errorMessage = $this->extractErrorMessage($response);
            Log::error('[PaddlePaymentGateway::updateSubscription] Paddle API error', [
                'subscription_id' => $subscriptionId,
                'status' => $response?->status(),
                'error' => $errorMessage,
            ]);

            return ['success' => false, 'error' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('[PaddlePaymentGateway::updateSubscription] Exception', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'An error occurred while updating the subscription: ' . $e->getMessage()];
        }
    }

    private function getPriceIdForPackage(Package $package): ?string
    {
        return $package->getPaddlePriceId($this)
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

        if (!isset($productIds[$packageKey])) {
            return null;
        }

        try {
            $url = rtrim($this->apiBaseUrl, '/') . "/products/{$productIds[$packageKey]}";
            $response = $this->makeApiRequest('get', $url);

            return $response ? ($response->json()['data'] ?? null) : null;
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
            $url = rtrim($this->apiBaseUrl, '/') . "/prices";
            $response = $this->makeApiRequest('get', $url, [
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
            $url = rtrim($this->apiBaseUrl, '/') . '/customers';
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if (empty($name)) {
                $name = $user->email;
            }

            $response = $this->makeApiRequest('post', $url, [
                'email' => $user->email,
                'name' => $name,
            ]);

            if ($response && $response->successful()) {
                $data = $response->json();
                $customerId = $data['data']['id'] ?? null;

                if ($customerId) {
                    $user->update(['paddle_customer_id' => $customerId]);
                    Log::info('[PaddlePaymentGateway::createOrGetCustomer] Paddle customer created', [
                        'user_id' => $user->id,
                        'customer_id' => $customerId,
                    ]);
                    return $customerId;
                }
            }

            Log::warning('[PaddlePaymentGateway::createOrGetCustomer] Failed to create Paddle customer', [
                'user_id' => $user->id,
                'status' => $response?->status(),
                'response' => $response?->body(),
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
                'post' => $request->withHeaders(['content-type' => 'application/json'])->post($url, $params),
                'patch' => $request->withHeaders(['content-type' => 'application/json'])->patch($url, $params),
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
}
