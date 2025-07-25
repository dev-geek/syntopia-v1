<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

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
        'payment_gateway_id',
        'subscription_starts_at',
        'package_id',
        'license_key',
        'is_subscribed',
        'tenant_id',
        'subscription_id',
        'paddle_customer_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'subscriber_password'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'subscription_starts_at' => 'datetime',
        'is_subscribed' => 'boolean',
        'password' => 'hashed',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateways::class, 'payment_gateway_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Cached accessors
    public function getSubscriptionStatusAttribute(): array
    {
        return Cache::remember("user_{$this->id}_subscription_status", 300, function () {
            return [
                'is_active' => $this->is_subscribed && $this->subscription_starts_at,
                'package_name' => $this->package?->name,
                'starts_at' => $this->subscription_starts_at,
                'gateway' => $this->paymentGateway?->name,
            ];
        });
    }

    public function getActiveOrdersAttribute()
    {
        return Cache::remember("user_{$this->id}_active_orders", 300, function () {
            return $this->orders()->where('status', 'pending')->get();
        });
    }

    // Clear cache when user data changes
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($user) {
            Cache::forget("user_{$user->id}_subscription_status");
            Cache::forget("user_{$user->id}_active_orders");
        });
    }

    // Subscription management methods
    public function hasActiveSubscription(): bool
    {
        return $this->is_subscribed &&
               $this->subscription_starts_at &&
               $this->subscription_starts_at->isPast();
    }

    public function canAccessPackage(string $packageName): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        return $this->package &&
               strtolower($this->package->name) === strtolower($packageName);
    }

    /**
     * Automatically hash the password when setting it
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Store the subscriber password as plain text for API usage
     *
     * @param string $value
     * @return void
     */
    public function setSubscriberPasswordAttribute(string $value): void
    {
        $this->attributes['subscriber_password'] = $value;
    }

    /**
     * Check if the user has a valid subscriber password for API calls
     *
     * @return bool
     */
    public function hasValidSubscriberPassword(): bool
    {
        if (empty($this->subscriber_password)) {
            return false;
        }

        // Validate against Xiaoice API password requirements
        $pattern = '/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/';
        return preg_match($pattern, $this->subscriber_password) === 1;
    }
}
