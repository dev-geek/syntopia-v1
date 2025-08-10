<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PaddleUpgradeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create payment gateway
        PaymentGateways::forceCreate([
            'name' => 'Paddle',
            'is_active' => true
        ]);
    }

    // Removed: scheduling upgrades at expiration is no longer supported

    public function test_paddle_immediate_upgrade()
    {
        // Create packages
        $starterPackage = Package::create([
            'name' => 'Starter',
            'price' => 39.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        $proPackage = Package::create([
            'name' => 'Pro',
            'price' => 79.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2', 'feature3'])
        ]);

        // Create user with starter package
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'package_id' => $starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => PaymentGateways::where('name', 'Paddle')->first()->id
        ]);

        // Create license for user
        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE-KEY-456',
            'package_id' => $starterPackage->id,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => PaymentGateways::where('name', 'Paddle')->first()->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        // Update user to reference the license
        $user->update(['user_license_id' => $license->id]);

        // Test immediate upgrade to Pro package
        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro', [
                'upgrade_at_expiration' => false
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'checkout_url',
                'message'
            ]);

        // Check that a pending upgrade order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $proPackage->id,
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => PaymentGateways::where('name', 'Paddle')->first()->id
        ]);
    }

    public function test_paddle_upgrade_webhook_processing()
    {
        // Create packages
        $starterPackage = Package::create([
            'name' => 'Starter',
            'price' => 39.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        $proPackage = Package::create([
            'name' => 'Pro',
            'price' => 79.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2', 'feature3'])
        ]);

        // Create user with starter package
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'package_id' => $starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => PaymentGateways::where('name', 'Paddle')->first()->id
        ]);

        // Create a pending upgrade order
        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $proPackage->id,
            'amount' => $proPackage->price,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway_id' => PaymentGateways::where('name', 'Paddle')->first()->id,
            'order_type' => 'upgrade',
            'transaction_id' => 'PADDLE-txn_789',
            'metadata' => [
                'original_package' => 'Starter',
                'upgrade_to' => 'Pro',
                'upgrade_type' => 'subscription_upgrade',
                'subscription_id' => 'PADDLE-sub_123456'
            ]
        ]);

        // Simulate Paddle webhook for transaction completion
        $webhookData = [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'PADDLE-txn_789',
                'subscription_id' => 'PADDLE-sub_123456',
                'custom_data' => [
                    'user_id' => $user->id,
                    'package' => 'Pro',
                    'upgrade_type' => 'subscription_upgrade'
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/paddle', $webhookData);

        $response->assertStatus(200);

        // Check that the order was completed
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed'
        ]);

        // Check that user's package was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'package_id' => $proPackage->id
        ]);
    }
}
