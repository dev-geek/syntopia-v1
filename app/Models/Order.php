<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    use HasFactory;
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
        'payment_method',
        'status',
        'paid_at',
    ];

    /**
     * The relationships to other models.
     */

    // Relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeUnpaid(Builder $query): void
    {
        $query->whereNull('paid_at');
    }

    public function scopePaid(Builder $query): void
    {
        $query->whereNotNull('paid_at');
    }

    public function scopeWithPaymentGateway(Builder $query, int $gatewayId): void
    {
        $query->where('payment_gateway_id', $gatewayId);
    }

    public function scopeWithPackage(Builder $query, int $packageId): void
    {
        $query->where('package_id', $packageId);
    }

    public function scopeRecent(Builder $query, int $limit = 10): void
    {
        $query->orderBy('created_at', 'desc')->take($limit);
    }
}
