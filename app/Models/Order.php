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
        'status',
        'currency'
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

}
