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
            Log::info('Sub Admin created', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'created_by' => auth()->id()
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->isSubAdmin()) {
            $changes = $user->getChanges();

            Log::info('Sub Admin updated', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'changes' => $changes,
                'updated_by' => auth()->id()
            ]);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        if ($user->isSubAdmin()) {
            Log::info('Sub Admin deleted', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'deleted_by' => auth()->id()
            ]);
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        if ($user->isSubAdmin()) {
            Log::info('Sub Admin restored', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'restored_by' => auth()->id()
            ]);
        }
    }
}
