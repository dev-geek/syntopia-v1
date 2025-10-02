<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SubAdminService
{
    public function createSubAdmin(array $data): User
    {
        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => Hash::make($data['password']),
            'subscriber_password' => $data['password'],
            'email_verified_at'   => now(), // Sub Admin is verified by default
            'status'              => $data['status'] ?? true,
        ]);

        $user->assignRole('Sub Admin');

        return $user;
    }

    public function updateSubAdmin(User $subadmin, array $data): User
    {
        $subadmin->update([
            'name'   => $data['name'],
            'status' => $data['status'] ?? $subadmin->status,
        ]);

        return $subadmin;
    }
}
