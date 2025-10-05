<?php

namespace App\Policies;

use App\Models\User;

class SubAdminPolicy
{
    /**
     * Determine whether the user can view any Sub Admins.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the Sub Admin.
     */
    public function view(User $user, User $subAdmin): bool
    {
        return $user->isSuperAdmin() && $subAdmin->isSubAdmin();
    }

    /**
     * Determine whether the user can create Sub Admins.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the Sub Admin.
     */
    public function update(User $user, User $subAdmin): bool
    {
        return $user->isSuperAdmin() && $subAdmin->isSubAdmin();
    }

    /**
     * Determine whether the user can delete the Sub Admin.
     */
    public function delete(User $user, User $subAdmin): bool
    {
        return $user->isSuperAdmin() && $subAdmin->isSubAdmin();
    }

    /**
     * Determine whether the user can restore the Sub Admin.
     */
    public function restore(User $user, User $subAdmin): bool
    {
        return $user->isSuperAdmin() && $subAdmin->isSubAdmin();
    }

    /**
     * Determine whether the user can permanently delete the Sub Admin.
     */
    public function forceDelete(User $user, User $subAdmin): bool
    {
        return $user->isSuperAdmin() && $subAdmin->isSubAdmin();
    }
}
