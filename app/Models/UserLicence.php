<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLicence extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
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
}
