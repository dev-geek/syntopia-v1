<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Models\Order;
use App\Models\UserLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PaddleDowngradeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $proPackage;
    protected $starterPackage;
    protected $paddleGateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test packages
        $this->proPackage = Package::create([
            'name' => 'Pro',
            'price' => 29.00,
            'duration' => 'month',
            'features' => ['feature1', 'feature2', 'feature3']
        ]);

        $this->starterPackage = Package::create([
            'name' => 'Starter',
            'price' => 9.00,
            'duration' => 'month',
            'features' => ['feature1', 'feature2']
        ]);

        // Create Paddle payment gateway
        $this->paddleGateway = PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);
    }

    public function test_downgrade_endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/payments/downgrade', [
            'package' => 'Starter'
        ]);

        $response->assertStatus(401);
    }

    public function test_downgrade_requires_package_parameter()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/downgrade', []);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Package name is required']);
    }

    public function test_downgrade_requires_active_subscription()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => false
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/downgrade', [
                'package' => 'Starter'
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Subscription Required']);
    }

    public function test_downgrade_requires_license_with_subscription_id()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => true,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id
        ]);

        // Create license without subscription_id
        UserLicence::create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'license_key' => 'test-license-key',
            'package_id' => $this->proPackage->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/downgrade', [
                'package' => 'Starter'
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'License Configuration Issue']);
    }

    public function test_downgrade_requires_payment_gateway()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => true,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => null
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'subscription_id' => 'sub_test123',
            'license_key' => 'test-license-key',
            'package_id' => $this->proPackage->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        // Update user to reference the license
        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/downgrade', [
                'package' => 'Starter'
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Payment Method Missing']);
    }

    public function test_downgrade_returns_checkout_url_for_valid_request()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => true,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id
        ]);

        UserLicence::create([
            'user_id' => $user->id,
            'subscription_id' => 'sub_test123',
            'license_key' => 'test-license-key',
            'package_id' => $this->proPackage->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        // Mock the Paddle API response
        $this->mock(\Illuminate\Support\Facades\Http::class, function ($mock) {
            $mock->shouldReceive('withHeaders')
                ->andReturnSelf();
            $mock->shouldReceive('get')
                ->andReturn(response()->json([
                    'data' => [
                        [
                            'id' => 'pro_123',
                            'name' => 'Starter',
                            'prices' => [
                                [
                                    'id' => 'pri_starter',
                                    'status' => 'active'
                                ]
                            ]
                        ]
                    ]
                ]));
            $mock->shouldReceive('post')
                ->andReturn(response()->json([
                    'data' => [
                        'id' => 'txn_123',
                        'checkout' => [
                            'url' => 'https://checkout.paddle.com/test'
                        ]
                    ]
                ]));
        });

        $response = $this->actingAs($user)
            ->postJson('/api/payments/downgrade', [
                'package' => 'Starter'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'checkout_url' => 'https://checkout.paddle.com/test'
            ]);
    }

    public function test_downgrade_creates_order_record()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => true,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id
        ]);

        UserLicence::create([
            'user_id' => $user->id,
            'subscription_id' => 'sub_test123',
            'license_key' => 'test-license-key',
            'package_id' => $this->proPackage->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        // Mock the Paddle API response
        $this->mock(\Illuminate\Support\Facades\Http::class, function ($mock) {
            $mock->shouldReceive('withHeaders')
                ->andReturnSelf();
            $mock->shouldReceive('get')
                ->andReturn(response()->json([
                    'data' => [
                        [
                            'id' => 'pro_123',
                            'name' => 'Starter',
                            'prices' => [
                                [
                                    'id' => 'pri_starter',
                                    'status' => 'active'
                                ]
                            ]
                        ]
                    ]
                ]));
            $mock->shouldReceive('post')
                ->andReturn(response()->json([
                    'data' => [
                        'id' => 'txn_123',
                        'checkout' => [
                            'url' => 'https://checkout.paddle.com/test'
                        ]
                    ]
                ]));
        });

        $this->actingAs($user)
            ->postJson('/api/payments/downgrade', [
                'package' => 'Starter'
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $this->starterPackage->id,
            'order_type' => 'downgrade',
            'subscription_id' => 'sub_test123',
            'status' => 'pending'
        ]);
    }
}
