<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure cached roles/permissions are cleared so newly seeded roles are visible immediately
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['Super Admin', 'Sub Admin', 'User'];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web'],
                []
            );
        }

        // Flush cache again after creation to prevent stale cache in long-running processes
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Roles seeded: ' . implode(', ', $roles));
    }
}
