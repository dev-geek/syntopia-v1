<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'package_id',
        'payment_gateway_id',
        'amount',
        'transaction_id',
        'status',
        'currency',
        'metadata',
        'order_type'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that placed the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package associated with the order.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the payment gateway used for the order.
     */
    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateways::class);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeCancellationScheduled(Builder $query): Builder
    {
        return $query->where('status', 'cancellation_scheduled');
    }

    public function scopeByTransactionId(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeByOrderType(Builder $query, string $orderType): Builder
    {
        return $query->where('order_type', $orderType);
    }

    public function scopeByPackage(Builder $query, int $packageId): Builder
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeByPaymentGateway(Builder $query, int $gatewayId): Builder
    {
        return $query->where('payment_gateway_id', $gatewayId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeExcludingStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', '!=', $status);
    }

    public function scopeExcludingStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereNotIn('status', $statuses);
    }

    public function scopeWithSubscriptionId(Builder $query, string $subscriptionId): Builder
    {
        return $query->where('metadata->subscription_id', $subscriptionId);
    }

    public function getFormattedAmountAttribute(): string
    {
        $currency = $this->currency ?? 'USD';
        $amount = number_format($this->amount, 2);
        return $currency . ' ' . $amount;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'completed' => 'Completed',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'cancellation_scheduled' => 'Cancellation Scheduled',
            'processing' => 'Processing',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsCancellationScheduledAttribute(): bool
    {
        return $this->status === 'cancellation_scheduled';
    }
}
