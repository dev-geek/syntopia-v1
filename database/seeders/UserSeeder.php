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
        // Ensure no duplicate entry
        $user = User::updateOrCreate(
            ['email' => 'admin@syntopia.io'], // Check if user already exists
            [
                'name' => 'Super Admin',
                'email' => 'admin@syntopia.io',
                'password' => Hash::make('admin@syntopia.io'),
                'status' => 1,
                'city' => 'Lahore',
                'pet' => 'Dog',
                'email_verified_at' => Carbon::now(), // Set email verified date
            ]
        );

        // Force update password to ensure it's correct
        $user->update(['password' => Hash::make('admin@syntopia.io')]);

        $user->assignRole('Super Admin');
    }
}
