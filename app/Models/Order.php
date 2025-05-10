<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'package',
        'amount',
        'payment',
    ];

    /**
     * The relationships to other models.
     */
    
    // Relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
