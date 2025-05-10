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
        User::updateOrCreate(
            ['email' => 'admin@syntopia.io'], // Check if user already exists
            [
                'name' => 'Super Admin',
                'email' => 'admin@syntopia.io',
                'password' => Hash::make('admin@syntopia.io'), // Securely hash the password
                'role' => 1,
                'status' => 1,
                'city' => 'Lahore',
                'pet' => 'Dog',
                'email_verified_at' => Carbon::now(), // Set email verified date
            ]
        );
    }
}