<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\{
    User,
    Package,
    Order,
    PaymentGateways,
    UserLicence
};
use App\Services\{
    License\LicenseApiService,
    FirstPromoterService,
    TenantAssignmentService
};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FastSpringPaymentGateway implements PaymentGatewayInterface
{
    private string $storefront;
    private string $username;
    private string $password;
    private string $webhookSecret;
    private array $addons;
    private array $productIds;
    private bool $useRedirectCallback;
    private string $apiBaseUrl;
    private bool $prorationEnabled;
    private ?User $user = null;
    private ?Order $order = null;

    public function __construct(
        private LicenseApiService $licenseApiService,
        private FirstPromoterService $firstPromoterService,
        private TenantAssignmentService $tenantAssignmentService,
    ) {
        $this->storefront = (string) config('payment.gateways.FastSpring.storefront', '');
        $this->username = (string) config('payment.gateways.FastSpring.username', '');
        $this->password = (string) config('payment.gateways.FastSpring.password', '');
        $this->webhookSecret = (string) config('payment.gateways.FastSpring.webhook_secret', '');
        $this->addons = (array) config('payment.gateways.FastSpring.addons', []);
        $this->productIds = (array) config('payment.gateways.FastSpring.product_ids', []);
        $this->useRedirectCallback = (bool) config('payment.gateways.FastSpring.use_redirect_callback', false);
        $this->apiBaseUrl = (string) config('payment.gateways.FastSpring.api_base_url', 'https://api.fastspring.com');
        $this->prorationEnabled = (bool) config('payment.gateways.FastSpring.proration_enabled', false);
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    // then create a method to process the payment
    public function processPayment(array $paymentData, bool $returnRedirect = true): array
    {
        return $this->createCheckout($paymentData, $returnRedirect);
    }

    // then create a method to create a checkout
    public function createCheckout(array $paymentData, bool $returnRedirect = true): array
    {
        Log::info('[FastSpringPaymentGateway::createCheckout] called', ['paymentData' => $paymentData, 'returnRedirect' => $returnRedirect]);

        $isUpgrade = (bool) ($paymentData['is_upgrade'] ?? false);

        if (!$this->user?->tenant_id) {
            $assignmentResult = $this->tenantAssignmentService->assignTenantWithRetry($this->user);

            if (!($assignmentResult['success'] ?? false) || empty($assignmentResult['data']['tenantId'] ?? null)) {
                Log::error('[FastSpringPaymentGateway::createCheckout] Failed to assign tenant before checkout', [
                    'user_id' => $this->user?->id,
                    'result'  => $assignmentResult,
                ]);

                return [
                    'success' => false,
                    'error'   => 'Account is not fully initialized (missing tenant). Please contact support.',
                ];
            }

            $this->user->refresh();
        }

        $licensePlan = $this->licenseApiService->resolvePlanLicense($this->user->tenant_id, $paymentData['package']);

        if (!$licensePlan) {
            Log::warning('[FastSpringPaymentGateway::createCheckout] No licenses available for requested plan', [
                'user_id'      => $this->user->id,
                'tenant_id'    => $this->user->tenant_id,
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
            $this->user->id . $paymentData['package'] . time(),
            $this->webhookSecret
        );

        $baseSuccessUrl = route('payments.success');

        $queryParams = [
            'referrer' => $this->user->id,
            'contactEmail' => $this->user->email,
            'contactFirstName' => $this->user->first_name ?? '',
            'contactLastName' => $this->user->last_name ?? '',
            'tags' => json_encode([
                'user_id'     => $this->user->id,
                'package'     => $paymentData['package'],
                'package_id'  => $this->order->package_id,
                'secure_hash' => $secureHash,
                'action'      => $isUpgrade ? 'upgrade' : 'new'
            ]),
            'mode' => 'popup',
            'successUrl' => $baseSuccessUrl . '?' . http_build_query([
                'gateway' => 'fastspring',
                'success-url' => $baseSuccessUrl,
                'transaction_id' => '{orderReference}',
                'popup' => 'true',
                'package_name' => $paymentData['package'],
                'payment_gateway_id' => $this->order->payment_gateway_id,
            ]),
            'cancelUrl' => route('payments.popup-cancel', [
                'gateway' => 'fastspring',
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
        $paymentData['is_upgrade'] = true;

        return $this->createCheckout($paymentData, $returnRedirect);
    }

    public function handleDowngrade(array $paymentData, bool $returnRedirect = true): array
    {
        if (!$this->user) {
            return [
                'success' => false,
                'error'   => 'User context not set for downgrade',
            ];
        }

        $currentPackage = $this->user->package;
        $targetPackageName = $paymentData['package'] ?? null;

        if (!$currentPackage || !$targetPackageName) {
            return [
                'success' => false,
                'error'   => 'Current or target package missing for downgrade',
            ];
        }

        if (!$this->user->tenant_id) {
            $assignmentResult = $this->tenantAssignmentService->assignTenantWithRetry($this->user);

            if (!($assignmentResult['success'] ?? false) || empty($assignmentResult['data']['tenantId'] ?? null)) {
                Log::error('[FastSpringPaymentGateway::handleDowngrade] Failed to assign tenant before downgrade', [
                    'user_id' => $this->user->id,
                    'result'  => $assignmentResult,
                ]);

                return [
                    'success' => false,
                    'error'   => 'Account is not fully initialized (missing tenant). Please contact support.',
                ];
            }

            $this->user->refresh();
        }

        $licensePlan = $this->licenseApiService->resolvePlanLicense($this->user->tenant_id, $targetPackageName);

        if (!$licensePlan) {
            Log::warning('[FastSpringPaymentGateway::handleDowngrade] No licenses available for downgrade plan', [
                'user_id'          => $this->user->id,
                'tenant_id'        => $this->user->tenant_id,
                'target_package'   => $targetPackageName,
                'current_package'  => $currentPackage->name,
            ]);

            return [
                'success' => false,
                'error'   => 'Licenses temporarily unavailable for the selected plan. Please try again later or contact support.',
            ];
        }

        $activeLicense = $this->user->userLicence;

        if (!$activeLicense) {
            return [
                'success' => false,
                'error'   => 'No active license found to schedule a downgrade. You can only downgrade from an active subscription.',
            ];
        }

        $expiresAt = $activeLicense->expires_at;

        if ($expiresAt && $expiresAt->isPast()) {
            return [
                'success' => false,
                'error'   => 'Active license already expired, cannot schedule downgrade.',
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

        $appliesAtPeriodEnd = true;

        return [
            'success'               => true,
            'current_package'       => $currentPackage->name,
            'target_package'        => $targetPackageName,
            'effective_date'        => $effectiveDate,
            'applies_at_period_end' => $appliesAtPeriodEnd,
        ];
    }

    // then create a method to handle the cancellation
    public function handleCancellation(array $paymentData, bool $returnRedirect = true): array
    {
        return [
            'success' => false,
            'error'   => 'FastSpring cancellation handling not implemented yet',
        ];
    }
}
