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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([\App\Observers\SubAdminObserver::class])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

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
        'is_active',
        'email_verified_at',
        'subscriber_password',
        'city',
        'pet',
        'verification_code',
        'payment_gateway_id',
        'package_id',
        'is_subscribed',
        'tenant_id',
        'paddle_customer_id',
        'user_license_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'subscriber_password'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_subscribed' => 'boolean',
        'is_active' => 'boolean',
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

    public function userLicence()
    {
        return $this->belongsTo(UserLicence::class, 'user_license_id');
    }

    public function userLogs()
    {
        return $this->hasMany(UserLog::class);
    }

    // Cached accessors
        public function getSubscriptionStatusAttribute(): array
    {
        return Cache::remember("user_{$this->id}_subscription_status", 300, function () {
            $activeLicense = $this->userLicence;
            $hasScheduledCancellation = $this->orders()
                ->where('status', 'cancellation_scheduled')
                ->exists();

            return [
                'is_active' => $this->is_subscribed && $activeLicense && $activeLicense->isActive(),
                'package_name' => $this->package?->name,
                'starts_at' => $activeLicense?->activated_at,
                'expires_at' => $activeLicense?->expires_at,
                'gateway' => $this->paymentGateway?->name,
                'has_scheduled_cancellation' => $hasScheduledCancellation,
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
               $this->userLicence &&
               $this->userLicence->isActive();
    }

    public function hasScheduledCancellation(): bool
    {
        return $this->orders()
            ->where('status', 'cancellation_scheduled')
            ->exists();
    }

    public function getCancellationInfo(): ?array
    {
        $cancellationOrder = $this->orders()
            ->where('status', 'cancellation_scheduled')
            ->latest('created_at')
            ->first();

        if (!$cancellationOrder) {
            return null;
        }

        return [
            'order_id' => $cancellationOrder->id,
            'cancelled_at' => $cancellationOrder->updated_at,
            'gateway' => $cancellationOrder->paymentGateway?->name,
            'package' => $cancellationOrder->package?->name,
            'effective_date' => $this->userLicence?->expires_at
        ];
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
     * @param string|null $value
     * @return void
     */
    public function setSubscriberPasswordAttribute(?string $value): void
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

    /**
     * Check if user is a returning customer who has previously purchased packages
     *
     * @return bool
     */
    public function isReturningCustomer(): bool
    {
        // Check if user has any completed orders
        $hasCompletedOrders = $this->orders()
            ->where('status', 'completed')
            ->exists();

        // Check if user has any order history at all
        $hasAnyOrderHistory = $this->orders()->exists();

        // Check if user has a payment gateway (indicates previous purchase)
        $hasPaymentGateway = $this->payment_gateway_id !== null;

        // Check if user has a package assigned
        $hasPackage = $this->package_id !== null;

        // Check if user is currently subscribed
        $isCurrentlySubscribed = $this->is_subscribed;

        // User is considered returning if they have any of these indicators
        return $hasCompletedOrders || $hasAnyOrderHistory || $hasPaymentGateway || $hasPackage || $isCurrentlySubscribed;
    }

    /**
     * Get user's purchase history summary
     *
     * @return array
     */
    public function getPurchaseHistory(): array
    {
        $orders = $this->orders()
            ->with(['package', 'paymentGateway'])
            ->orderBy('created_at', 'desc')
            ->get();

        $completedOrders = $orders->where('status', 'completed');
        $pendingOrders = $orders->where('status', 'pending');
        $failedOrders = $orders->where('status', 'failed');

        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completedOrders->count(),
            'pending_orders' => $pendingOrders->count(),
            'failed_orders' => $failedOrders->count(),
            'first_purchase_date' => $orders->first() ? $orders->first()->created_at : null,
            'last_purchase_date' => $completedOrders->first() ? $completedOrders->first()->created_at : null,
            'total_spent' => $completedOrders->sum('amount'),
            'current_package' => $this->package ? $this->package->name : null,
            'payment_gateway' => $this->paymentGateway ? $this->paymentGateway->name : null,
            'is_currently_subscribed' => $this->is_subscribed,
            'has_active_license' => $this->userLicence && $this->userLicence->isActive(),
        ];
    }

    /**
     * Check if user is a new customer (no purchase history)
     *
     * @return bool
     */
    public function isNewCustomer(): bool
    {
        return !$this->isReturningCustomer();
    }

    /**
     * Check if user is a Sub Admin
     *
     * @return bool
     */
    public function isSubAdmin(): bool
    {
        return $this->hasRole('Sub Admin');
    }

    /**
     * Check if user is a Super Admin
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * Check if Sub Admin is active and can login
     *
     * @return bool
     */
    public function canSubAdminLogin(): bool
    {
        return $this->isSubAdmin() && $this->is_active;
    }

    /**
     * Scope to get only Sub Admins
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubAdmins($query)
    {
        return $query->role('Sub Admin');
    }

    /**
     * Scope to get only active Sub Admins
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveSubAdmins($query)
    {
        return $query->role('Sub Admin')->where('is_active', true);
    }
}
