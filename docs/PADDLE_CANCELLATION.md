# Paddle Subscription Cancellation at Expiration

This document explains how Paddle subscription cancellation at expiration is implemented in the Syntopia application.

## Overview

When a user cancels their Paddle subscription, the cancellation is scheduled to take effect at the end of the current billing period, not immediately. This ensures that users continue to have access to their subscription until the period they've already paid for expires.

## Implementation Details

### 1. Cancellation Request Flow

When a user requests to cancel their subscription:

1. **API Endpoint**: `POST /api/cancel-subscription`
2. **Controller Method**: `PaymentController@cancelSubscription`
3. **Paddle API Call**: Uses `PaddleClient@cancelSubscription` with `billingPeriod = 1`

### 2. PaddleClient Implementation

```php
public function cancelSubscription(string $subscriptionId, int $billingPeriod = 1)
{
    $effectiveFrom = $billingPeriod === 0 ? 'immediately' : 'next_billing_period';
    
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json'
    ])->post("{$this->apiBaseUrl}/subscriptions/{$subscriptionId}/cancel", [
        'effective_from' => $effectiveFrom
    ]);
    
    // Returns response data or null on failure
}
```

**Parameters**:
- `$billingPeriod = 0`: Cancellation takes effect immediately
- `$billingPeriod = 1` (default): Cancellation takes effect at next billing period

### 3. Database Status Tracking

When cancellation is scheduled:

1. **Order Status**: Updated to `cancellation_scheduled`
2. **User Status**: Remains `is_subscribed = true` until expiration
3. **License**: Remains active until expiration

### 4. User Model Methods

```php
// Check if user has scheduled cancellation
$user->hasScheduledCancellation(): bool

// Get cancellation details
$user->getCancellationInfo(): ?array

// Get subscription status including cancellation info
$user->subscription_status: array
```

### 5. Webhook Processing

When the subscription actually expires, Paddle sends a webhook:

**Webhook Endpoint**: `POST /api/webhooks/paddle`
**Event Type**: `subscription.cancelled`

The webhook handler (`handlePaddleSubscriptionCancelled`) processes the cancellation:

1. Finds the user by subscription ID
2. Deletes the user license record
3. Resets user subscription data
4. Updates order status to `canceled`
5. Logs the cancellation

## User Experience

### During Cancellation Request
- User remains subscribed and can access their package
- Order status shows as "cancellation_scheduled"
- User sees message: "Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period."

### After Expiration
- User loses access to the package
- Subscription is completely removed from the system
- All related records are cleaned up

## Testing

Run the Paddle cancellation tests:

```bash
php artisan test tests/Feature/PaddleCancellationTest.php
```

## Configuration

Ensure Paddle webhook endpoint is configured in your Paddle dashboard:

- **URL**: `https://yourdomain.com/api/webhooks/paddle`
- **Events**: `subscription.cancelled`

## Logging

The system logs all cancellation events:

- Cancellation requests
- Paddle API responses
- Webhook processing
- Database updates

Check logs for troubleshooting:
```bash
tail -f storage/logs/laravel.log | grep -i paddle
```

## Error Handling

- **API Failures**: Returns error response to user
- **Webhook Failures**: Logs error and returns 500 status
- **Missing Data**: Graceful fallbacks and logging

## Security

- Webhook signature verification (if configured)
- User authentication for cancellation requests
- Database transactions for data consistency 
