<?php

namespace App\Contracts\Payment;

use App\Models\User;
use App\Models\Package;
use App\Models\Order;

interface PaymentGatewayInterface
{
    public function createCheckout(User $user, Package $package, array $options = []): array;

    public function processPayment(array $paymentData, bool $returnRedirect = true);

    public function processWebhook(array $payload): array;

    public function processSuccessCallback(array $requestData): array;

    public function createUpgradeCheckout(User $user, Package $package, string $subscriptionId): string;

    public function createDowngradeCheckout(User $user, Package $package, string $subscriptionId): string;

    public function cancelSubscription(User $user, string $subscriptionId, ?int $cancellationReasonId = null, ?string $reasonText = null): array;

    public function verifyTransaction(string $transactionId): ?array;

    public function getName(): string;

    public function upgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null);

    public function downgradeSubscription(string $subscriptionId, string $newProductId, ?string $prorationBillingMode = null);

    public function downgradeSubscriptionForUser(User $user, string $subscriptionId, string $newProductId);
}
