<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\FreePlanAttemptObserver;

#[ObservedBy([FreePlanAttemptObserver::class])]
class FreePlanAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'fingerprint_id',
        'email',
        'is_blocked',
        'blocked_at',
        'block_reason',
        'data',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
        'data' => 'array',
    ];

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function scopeNotBlocked($query)
    {
        return $query->where('is_blocked', false);
    }

    public function scopeByIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    public function scopeByDeviceFingerprint($query, $fingerprint)
    {
        return $query->where('device_fingerprint', $fingerprint);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByFingerprintId($query, $fingerprintId)
    {
        return $query->where('fingerprint_id', $fingerprintId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function block($reason = null)
    {
        $this->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);
    }

    public function unblock()
    {
        $this->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'block_reason' => null,
        ]);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'email', 'email');
    }
}
