<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLicence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'license_key',
        'package_id',
        'subscription_id',
        'payment_gateway_id',
        'activated_at',
        'expires_at',
        'is_active',
        'metadata',
        'is_upgrade_license',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'is_upgrade_license' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateways::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'user_license_id');
    }

    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'expires_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
        });
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPackage($query, int $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeByPaymentGateway($query, int $gatewayId)
    {
        return $query->where('payment_gateway_id', $gatewayId);
    }

    public function scopeBySubscriptionId($query, string $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeByLicenseKey($query, string $licenseKey)
    {
        return $query->where('license_key', $licenseKey);
    }

    public function scopeUpgradeLicenses($query)
    {
        return $query->where('is_upgrade_license', true);
    }

    public function scopeRegularLicenses($query)
    {
        return $query->where('is_upgrade_license', false);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays($days));
    }

    public function scopeActivatedAfter($query, $date)
    {
        return $query->where('activated_at', '>=', $date);
    }

    public function scopeActivatedBefore($query, $date)
    {
        return $query->where('activated_at', '<=', $date);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getFormattedExpiresAtAttribute(): ?string
    {
        return $this->expires_at?->format('Y-m-d H:i:s');
    }

    public function getFormattedActivatedAtAttribute(): ?string
    {
        return $this->activated_at?->format('Y-m-d H:i:s');
    }
}
