<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'facebook_id',
        'status',
        'email_verified_at',
        'subscriber_password',
        'city',
        'pet',
        'verification_code',
        'payment_gateway',
        'package_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'string'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrderPackageName()
    {
        // Get the latest order and return its associated package name
        return $this->orders()->latest()->first()?->package->name ?? null;
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateways::class);
    }

}
