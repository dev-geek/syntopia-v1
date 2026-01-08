<?php

namespace App\Observers;

use App\Models\FreePlanAttempt;
use Illuminate\Support\Facades\Log;

class FreePlanAttemptObserver
{
    public function created(FreePlanAttempt $freePlanAttempt): void
    {
        }

    public function updated(FreePlanAttempt $freePlanAttempt): void
    {
        if ($freePlanAttempt->isDirty('is_blocked') && $freePlanAttempt->is_blocked) {
            Log::warning('Free plan attempt blocked', [
                'id' => $freePlanAttempt->id,
                'ip_address' => $freePlanAttempt->ip_address,
                'email' => $freePlanAttempt->email,
                'block_reason' => $freePlanAttempt->block_reason,
            ]);
        }
    }
} 