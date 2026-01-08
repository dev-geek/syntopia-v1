<?php

namespace App\Listeners;

use Laravel\Paddle\Events\WebhookReceived;
use Illuminate\Support\Facades\Log;
use App\Jobs\NotifyUserOfFailedPayment;
use App\Models\{
    User,
    UserLicence,
    Order,
    PaymentGateways,
    Package
};
use App\Services\FirstPromoterService;

class HandlePaddleWebhook
{
    public function __construct(
        private FirstPromoterService $firstPromoterService
    ) {
    }

    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $alertName = $payload['alert_name'] ?? null;
        $eventType = $payload['event_type'] ?? null;

        $webhookType = $alertName ?? $eventType;

        // Log to billing channel for monitoring
        Log::channel('billing')->info('Paddle webhook received', [
            'event' => $webhookType,
            'alert_name' => $alertName,
            'event_type' => $eventType,
            'customer' => $event->billable->id ?? null,
            'payload' => $payload
        ]);

        try {
        match ($webhookType) {
            'subscription_payment_failed', 'subscription.payment_failed' => $this->handlePaymentFailed($event),
            'subscription_payment_succeeded', 'subscription.payment_succeeded' => $this->handlePaymentSucceeded($event),
            'subscription_updated', 'subscription.updated' => $this->handleSubscriptionUpdated($event),
            'subscription_cancelled', 'subscription.cancelled', 'subscription.canceled' => $this->handleSubscriptionCancelled($event),
                default => Log::channel('billing')->debug('Unhandled Paddle webhook', [
                    'event' => $webhookType,
                'alert_name' => $alertName,
                'event_type' => $eventType,
                    'customer' => $event->billable->id ?? null
                ])
            };
        } catch (\Exception $e) {
            Log::channel('billing')->error('Paddle webhook processing failed', [
                'event' => $webhookType,
                'customer' => $event->billable->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function handlePaymentFailed(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];
        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;

        // Use billable from event if available (Laravel Paddle provides this)
        $user = $event->billable ?? null;

        // Fallback: Find user from subscription or customer ID
        if (!$user) {
        if ($subscriptionId) {
            $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
            $user = $userLicense ? $userLicense->user : User::where('subscription_id', $subscriptionId)->first();
        }

        if (!$user && $customerId) {
            $user = User::where('paddle_customer_id', $customerId)->first();
            }
        }

        if (!$user) {
            Log::channel('billing')->warning('Paddle payment failed webhook received but user not found', [
                'event' => 'subscription_payment_failed',
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'payload' => $payload
            ]);
            return;
        }

        Log::channel('billing')->warning('Paddle payment failed', [
            'event' => 'subscription_payment_failed',
            'customer' => $user->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'subscription_id' => $subscriptionId,
            'payload' => $payload
        ]);

        try {
        // Update user's subscription status
        $user->update(['is_subscribed' => false]);

        // Update license status if exists
        if ($user->userLicence) {
            $user->userLicence->update([
                'status' => 'payment_failed',
                'updated_at' => now()
            ]);
        }

        // Create order record for failed payment
        Order::create([
            'user_id' => $user->id,
            'package_id' => $user->package_id,
            'order_type' => 'payment_failed',
            'status' => 'failed',
            'transaction_id' => 'FAILED-' . $user->id . '-' . uniqid(),
            'amount' => $data['amount'] ?? $payload['amount'] ?? 0,
            'currency' => $data['currency'] ?? $payload['currency'] ?? 'USD',
            'payment_gateway_id' => $user->payment_gateway_id,
            'metadata' => [
                'webhook_payload' => $payload,
                'failed_at' => now()->toDateTimeString(),
                'subscription_id' => $subscriptionId,
            ]
        ]);

        // Dispatch job to notify user
        NotifyUserOfFailedPayment::dispatch($user, $payload);

            Log::channel('billing')->info('Paddle payment failed handled successfully', [
                'event' => 'subscription_payment_failed',
                'customer' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::channel('billing')->error('Failed to handle Paddle payment failed webhook', [
                'event' => 'subscription_payment_failed',
                'customer' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function handlePaymentSucceeded(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];
        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;

        // Use billable from event if available
        $user = $event->billable ?? null;

        // Fallback: Find user from subscription or customer ID
        if (!$user) {
        if ($subscriptionId) {
            $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
            $user = $userLicense ? $userLicense->user : User::where('subscription_id', $subscriptionId)->first();
        }

        if (!$user && $customerId) {
            $user = User::where('paddle_customer_id', $customerId)->first();
            }
        }

        if (!$user) {
            Log::channel('billing')->warning('Paddle payment succeeded webhook received but user not found', [
                'event' => 'subscription_payment_succeeded',
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId
            ]);
            return;
        }

        Log::channel('billing')->info('Paddle webhook processed', [
            'event' => 'subscription_payment_succeeded',
            'customer' => $user->id
        ]);

        try {
        // Ensure user is marked as subscribed
        $user->update(['is_subscribed' => true]);

        // Update license if exists
        if ($user->userLicence) {
            $user->userLicence->update([
                'status' => 'active',
                'updated_at' => now()
            ]);
        }

        // Track FirstPromoter sale for recurring payments
        $this->trackFirstPromoterSaleFromWebhook($user, $payload, $data);

        } catch (\Exception $e) {
            Log::channel('billing')->error('Failed to handle Paddle payment succeeded webhook', [
                'event' => 'subscription_payment_succeeded',
                'customer' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function trackFirstPromoterSaleFromWebhook(User $user, array $payload, array $data): void
    {
        try {
            // Get transaction ID from webhook data
            $transactionId = $data['transaction_id']
                ?? $data['id']
                ?? $payload['transaction_id']
                ?? $payload['id']
                ?? null;

            if (!$transactionId) {
                Log::channel('billing')->debug('[HandlePaddleWebhook] Skipping FirstPromoter tracking: no transaction_id in webhook', [
                    'user_id' => $user->id,
                    'payload_keys' => array_keys($payload),
                    'data_keys' => array_keys($data),
                ]);
                return;
            }

            // Get amount from webhook (Paddle sends amount in cents)
            $amountInCents = $data['amount'] ?? $payload['amount'] ?? null;
            if (!$amountInCents || $amountInCents <= 0) {
                Log::channel('billing')->debug('[HandlePaddleWebhook] Skipping FirstPromoter tracking: invalid amount', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'amount' => $amountInCents,
                ]);
                return;
            }

            // Convert from cents to dollars for FirstPromoterService (it will convert back to cents)
            $amountInDollars = (float) ($amountInCents / 100);

            // Get package from user
            $package = $user->package;
            if (!$package) {
                Log::channel('billing')->warning('[HandlePaddleWebhook] User has no package for FirstPromoter tracking', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                ]);
                return;
            }

            // Get gateway record
            $gatewayRecord = PaymentGateways::whereRaw('LOWER(name) = ?', ['paddle'])->first();

            // Extract custom data from webhook payload
            $customData = $data['custom_data']
                ?? $payload['custom_data']
                ?? $data['custom']
                ?? $payload['custom']
                ?? null;

            if (is_string($customData)) {
                try {
                    $customData = json_decode($customData, true);
                } catch (\Throwable $e) {
                    Log::channel('billing')->warning('[HandlePaddleWebhook] Failed to parse custom_data', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    $customData = null;
                }
            }

            if (!is_array($customData)) {
                $customData = [];
            }

            // Extract tracking IDs
            $tid = $customData['fp_tid']
                ?? $customData['tid']
                ?? null;

            $refId = $customData['ref_id']
                ?? null;

            // Prepare tracking data
            $trackingData = [
                'event_id' => (string) $transactionId,
                'amount' => $amountInDollars,
                'currency' => $data['currency'] ?? $payload['currency'] ?? 'USD',
                'email' => $user->email,
                'uid' => (string) $user->id,
                'plan' => $package->name,
            ];

            if ($tid) {
                $trackingData['tid'] = $tid;
            }

            if ($refId) {
                $trackingData['ref_id'] = $refId;
            }

            // Track the sale
            $result = $this->firstPromoterService->trackSale($trackingData);

            if ($result === null) {
                Log::channel('billing')->warning('[HandlePaddleWebhook] FirstPromoter tracking returned null', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                ]);
            } elseif (isset($result['duplicate']) && $result['duplicate']) {
                Log::channel('billing')->info('[HandlePaddleWebhook] FirstPromoter tracking: duplicate sale detected', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                ]);
            } else {
                Log::channel('billing')->info('[HandlePaddleWebhook] FirstPromoter sale tracked successfully', [
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'sale_id' => $result['id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // Don't throw - log and continue webhook processing
            Log::channel('billing')->error('[HandlePaddleWebhook] Failed to track FirstPromoter sale from webhook', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function handleSubscriptionUpdated(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];
        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;

        // Use billable from event if available
        $user = $event->billable ?? null;

        // Fallback: Find user from subscription or customer ID
        if (!$user) {
        if ($subscriptionId) {
            $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
            $user = $userLicense ? $userLicense->user : User::where('subscription_id', $subscriptionId)->first();
        }

        if (!$user && $customerId) {
            $user = User::where('paddle_customer_id', $customerId)->first();
            }
        }

        if (!$user) {
            Log::channel('billing')->warning('Paddle subscription updated webhook received but user not found', [
                'event' => 'subscription_updated',
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId
            ]);
            return;
        }

        Log::channel('billing')->info('Paddle webhook processed', [
            'event' => 'subscription_updated',
            'customer' => $user->id
        ]);
    }

    protected function handleSubscriptionCancelled(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];
        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;

        // Use billable from event if available
        $user = $event->billable ?? null;

        // Fallback: Find user from subscription or customer ID
        if (!$user) {
        if ($subscriptionId) {
            $userLicense = UserLicence::where('subscription_id', $subscriptionId)->first();
            $user = $userLicense ? $userLicense->user : User::where('subscription_id', $subscriptionId)->first();
        }

        if (!$user && $customerId) {
            $user = User::where('paddle_customer_id', $customerId)->first();
            }
        }

        if (!$user) {
            Log::channel('billing')->warning('Paddle subscription cancelled webhook received but user not found', [
                'event' => 'subscription_cancelled',
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId
            ]);
            return;
        }

        Log::channel('billing')->info('Paddle webhook processed', [
            'event' => 'subscription_cancelled',
            'customer' => $user->id
        ]);

        try {
        // Update user's subscription status
        $user->update(['is_subscribed' => false]);

        // Update license status if exists
        if ($user->userLicence) {
            $user->userLicence->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);
            }
        } catch (\Exception $e) {
            Log::channel('billing')->error('Failed to handle Paddle subscription cancelled webhook', [
                'event' => 'subscription_cancelled',
                'customer' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
