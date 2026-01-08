<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class SubAdminObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if ($user->isSubAdmin()) {
            }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->isSubAdmin()) {
            $changes = $user->getChanges();

            }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        if ($user->isSubAdmin()) {
            }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        if ($user->isSubAdmin()) {
            }
    }
}
