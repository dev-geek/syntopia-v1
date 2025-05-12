<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateways extends Model
{
    use HasFactory;
    protected $guarded = [
        'user_id',
        'name',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
