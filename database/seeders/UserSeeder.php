<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // First, try to find the user
        $user = User::where('email', 'admin@syntopia.ai')->first();

        // If user doesn't exist, create with hashed password
        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Super Admin',
                'email' => 'admin@syntopia.ai',
                'password' => Hash::make('admin@syntopia.ai'),
                'status' => 1,
                'city' => 'London',
                'pet' => 'Dog',
                'email_verified_at' => Carbon::now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = User::find($userId);
        } else {
            // Update existing user with direct DB query to avoid model events
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make('admin@syntopia.ai'),
                    'status' => 1,
                    'email_verified_at' => Carbon::now(),
                    'updated_at' => now(),
                ]);
            $user->refresh();
        }

        // Ensure the role is assigned
        if (!$user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }

        // Verify password was set correctly
        $check = Hash::check('admin@syntopia.ai', $user->password);

        $this->command->info('Admin user has been set up.');
        $this->command->info('Email: admin@syntopia.ai');
        $this->command->info('Password: admin@syntopia.ai');
        $this->command->info('Password check: ' . ($check ? 'PASSED' : 'FAILED'));
    }
}
