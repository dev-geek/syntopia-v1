<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = \App\Models\User::where('role', 1)->first();

        if (!$admin) {
            $this->command->warn('No admin user found. Skipping PaymentGatewaySeeder.');
            return;
        }

        DB::table('payment_gateways')->insert([
            [
                'name' => 'Pay Pro Global',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FastSpring',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paddle',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('Payment gateways seeded successfully.');
    }
}
