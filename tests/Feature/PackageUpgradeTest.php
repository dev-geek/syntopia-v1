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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackageUpgradeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $starterPackage;
    protected $proPackage;
    protected $businessPackage;
    protected $fastspringGateway;
    protected $paddleGateway;
    protected $payproglobalGateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test packages
        $this->starterPackage = Package::create([
            'name' => 'Starter',
            'price' => 39.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2']),
            'fastspring_product_id' => 'starter-plan',
            'paddle_product_id' => 'prod_starter',
            'payproglobal_product_id' => 'PPG_STARTER'
        ]);

        $this->proPackage = Package::create([
            'name' => 'Pro',
            'price' => 79.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2', 'feature3']),
            'fastspring_product_id' => 'pro-plan',
            'paddle_product_id' => 'prod_pro',
            'payproglobal_product_id' => 'PPG_PRO'
        ]);

        $this->businessPackage = Package::create([
            'name' => 'Business',
            'price' => 149.00,
            'duration' => 30,
            'features' => json_encode(['feature1', 'feature2', 'feature3', 'feature4']),
            'fastspring_product_id' => 'business-plan',
            'paddle_product_id' => 'prod_business',
            'payproglobal_product_id' => 'PPG_BUSINESS'
        ]);

        // Create payment gateways
        $this->fastspringGateway = PaymentGateways::create([
            'name' => 'FastSpring',
            'is_active' => true
        ]);

        $this->paddleGateway = PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->payproglobalGateway = PaymentGateways::create([
            'name' => 'Pay Pro Global',
            'is_active' => true
        ]);
    }

    /**
     * Test FastSpring package upgrade
     */
    public function test_fastspring_package_upgrade_creates_order_and_checkout_url()
    {
        // Create user with Starter package and FastSpring subscription
        $user = User::create([
            'name' => 'FastSpring User',
            'email' => 'fastspring@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        // Create active license
        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'FS-LICENSE-123',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        // Make upgrade request
        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Upgrade order created successfully'
            ])
            ->assertJsonStructure([
                'success',
                'checkout_url',
                'message'
            ]);

        // Assert order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $this->proPackage->id,
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        // Assert checkout URL contains FastSpring base URL
        $responseData = $response->json();
        $this->assertStringContainsString('onfastspring.com', $responseData['checkout_url']);
    }

    /**
     * Test Paddle package upgrade
     */
    public function test_paddle_package_upgrade_creates_order_and_checkout_url()
    {
        // Create user with Starter package and Paddle subscription
        $user = User::create([
            'name' => 'Paddle User',
            'email' => 'paddle@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => $this->paddleGateway->id,
            'paddle_customer_id' => 'ctm_123456'
        ]);

        // Create active license
        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'PADDLE-LICENSE-123',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => $this->paddleGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        // Mock Paddle API responses for product lookup
        Http::fake([
            'sandbox-api.paddle.com/products*' => Http::response([
                'data' => [
                    [
                        'id' => 'prod_pro',
                        'name' => 'Pro',
                        'prices' => [
                            [
                                'id' => 'pri_pro_123',
                                'status' => 'active'
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Make upgrade request
        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Upgrade order created successfully'
            ])
            ->assertJsonStructure([
                'success',
                'checkout_url',
                'message'
            ]);

        // Assert order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $this->proPackage->id,
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->paddleGateway->id
        ]);

        // Assert checkout URL contains Paddle base URL
        $responseData = $response->json();
        $this->assertStringContainsString('paddle.com', $responseData['checkout_url']);
    }

    /**
     * Test Pay Pro Global package upgrade
     */
    public function test_payproglobal_package_upgrade_creates_order_and_checkout_url()
    {
        // Create user with Starter package and Pay Pro Global subscription
        $user = User::create([
            'name' => 'PayProGlobal User',
            'email' => 'payproglobal@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PPG-sub_123456',
            'payment_gateway_id' => $this->payproglobalGateway->id
        ]);

        // Create active license
        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'PPG-LICENSE-123',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'PPG-sub_123456',
            'payment_gateway_id' => $this->payproglobalGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        // Make upgrade request
        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Upgrade order created successfully'
            ])
            ->assertJsonStructure([
                'success',
                'checkout_url',
                'message'
            ]);

        // Assert order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $this->proPackage->id,
            'status' => 'pending',
            'order_type' => 'upgrade',
            'payment_gateway_id' => $this->payproglobalGateway->id
        ]);

        // Assert checkout URL contains Pay Pro Global base URL
        $responseData = $response->json();
        $this->assertStringContainsString('payproglobal.com', $responseData['checkout_url']);
    }

    /**
     * Test upgrade fails when user has no active subscription
     */
    public function test_upgrade_fails_without_active_subscription()
    {
        $user = User::create([
            'name' => 'No Subscription User',
            'email' => 'nosub@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => false,
            'subscription_id' => null,
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Subscription Required'
            ]);
    }

    /**
     * Test upgrade fails with invalid package
     */
    public function test_upgrade_fails_with_invalid_package()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/InvalidPackage');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid package selected'
            ]);
    }

    /**
     * Test upgrade fails when payment gateway cannot be determined
     */
    public function test_upgrade_fails_when_payment_gateway_cannot_be_determined()
    {
        $user = User::create([
            'name' => 'No Gateway User',
            'email' => 'nogateway@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'UNKNOWN-sub_123456',
            'payment_gateway_id' => null
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'UNKNOWN-sub_123456',
            'payment_gateway_id' => null,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Payment Method Not Found'
            ]);
    }

    /**
     * Test upgrade detects gateway from subscription ID prefix (Paddle)
     */
    public function test_upgrade_detects_paddle_gateway_from_subscription_id_prefix()
    {
        $user = User::create([
            'name' => 'Paddle Prefix User',
            'email' => 'paddleprefix@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => null
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'PADDLE-sub_123456',
            'payment_gateway_id' => null,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        Http::fake([
            'sandbox-api.paddle.com/*' => Http::response([
                'data' => [
                    'id' => 'txn_789',
                    'checkout' => [
                        'url' => 'https://sandbox-checkout.paddle.com/checkout/txn_789'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test upgrade detects gateway from subscription ID prefix (FastSpring)
     */
    public function test_upgrade_detects_fastspring_gateway_from_subscription_id_prefix()
    {
        $user = User::create([
            'name' => 'FastSpring Prefix User',
            'email' => 'fastspringprefix@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => null
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => null,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test upgrade detects gateway from subscription ID prefix (Pay Pro Global)
     */
    public function test_upgrade_detects_payproglobal_gateway_from_subscription_id_prefix()
    {
        $user = User::create([
            'name' => 'PayProGlobal Prefix User',
            'email' => 'payproglobalprefix@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'PPG-sub_123456',
            'payment_gateway_id' => null
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'PPG-sub_123456',
            'payment_gateway_id' => null,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test upgrade order metadata contains correct information
     */
    public function test_upgrade_order_metadata_contains_correct_information()
    {
        $user = User::create([
            'name' => 'Metadata Test User',
            'email' => 'metadata@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(200);

        $order = Order::where('user_id', $user->id)
            ->where('order_type', 'upgrade')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('Starter', $order->metadata['original_package']);
        $this->assertEquals('Pro', $order->metadata['upgrade_to']);
        $this->assertEquals('subscription_upgrade', $order->metadata['upgrade_type']);
        $this->assertEquals('FS-sub_123456', $order->metadata['subscription_id']);
    }

    /**
     * Test upgrade from Starter to Business (multiple level upgrade)
     */
    public function test_upgrade_from_starter_to_business_package()
    {
        $user = User::create([
            'name' => 'Multi Level Upgrade User',
            'email' => 'multilevel@example.com',
            'password' => bcrypt('password'),
            'package_id' => $this->starterPackage->id,
            'is_subscribed' => true,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        $license = UserLicence::create([
            'user_id' => $user->id,
            'license_key' => 'TEST-LICENSE',
            'package_id' => $this->starterPackage->id,
            'subscription_id' => 'FS-sub_123456',
            'payment_gateway_id' => $this->fastspringGateway->id,
            'activated_at' => now(),
            'is_active' => true
        ]);

        $user->update(['user_license_id' => $license->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/upgrade/Business');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $order = Order::where('user_id', $user->id)
            ->where('order_type', 'upgrade')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals($this->businessPackage->id, $order->package_id);
        $this->assertEquals($this->businessPackage->price, $order->amount);
    }

    /**
     * Test upgrade requires authentication
     */
    public function test_upgrade_requires_authentication()
    {
        $response = $this->postJson('/api/payments/upgrade/Pro');

        $response->assertStatus(401);
    }
}

