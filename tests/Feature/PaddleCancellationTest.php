<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\UserLicence;
use App\Models\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaddleCancellationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected PaymentGateways $paddleGateway;
    protected Package $package;
    protected User $user;
    protected UserLicence $userLicense;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Disable webhook signature verification for tests
        config(['payment.gateways.Paddle.webhook_secret' => null]);

        // Create test payment gateway
        $this->paddleGateway = PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        // Create test package
        $this->package = Package::factory()->create([
            'name' => 'Test Package',
            'price' => 29.99
        ]);

        // Create test user
        $this->user = User::factory()->create([
            'is_subscribed' => true,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 1,
            'email_verified_at' => now()
        ]);

        // Create user license
        $this->userLicense = UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'subscription_id' => 'PADDLE-TEST-SUB-123',
            'license_key' => 'TEST-LICENSE-KEY-123',
            'is_active' => true,
            'activated_at' => now(),
            'expires_at' => now()->addMonth()
        ]);

        // Update user with license reference
        $this->user->update(['user_license_id' => $this->userLicense->id]);

        // Create order
        $this->order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 'completed',
            'amount' => 29.99,
            'transaction_id' => 'PADDLE-TEST-TXN-123',
            'metadata' => [
                'subscription_id' => 'PADDLE-TEST-SUB-123'
            ]
        ]);
    }

    public function test_user_has_scheduled_cancellation()
    {
        // Create a cancellation scheduled order
        Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 'cancellation_scheduled',
            'amount' => 0,
            'transaction_id' => 'PADDLE-TEST-TXN-456',
            'metadata' => [
                'subscription_id' => 'PADDLE-TEST-SUB-123'
            ]
        ]);

        $this->assertTrue($this->user->hasScheduledCancellation());

        $cancellationInfo = $this->user->getCancellationInfo();
        $this->assertNotNull($cancellationInfo);
        $this->assertEquals('Paddle', $cancellationInfo['gateway']);
        $this->assertEquals('Test Package', $cancellationInfo['package']);
    }

    public function test_user_does_not_have_scheduled_cancellation()
    {
        $this->assertFalse($this->user->hasScheduledCancellation());
        $this->assertNull($this->user->getCancellationInfo());
    }

    public function test_subscription_status_includes_cancellation_info()
    {
        // Create a cancellation scheduled order
        Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 'cancellation_scheduled',
            'amount' => 0,
            'transaction_id' => 'PADDLE-TEST-TXN-456',
            'metadata' => [
                'subscription_id' => 'PADDLE-TEST-SUB-123'
            ]
        ]);

        $status = $this->user->subscription_status;

        $this->assertTrue($status['is_active']);
        $this->assertTrue($status['has_scheduled_cancellation']);
        $this->assertEquals('Test Package', $status['package_name']);
        $this->assertEquals('Paddle', $status['gateway']);
    }

    public function test_cancel_subscription_api_endpoint()
    {
        // Mock HTTP response for Paddle API
        \Illuminate\Support\Facades\Http::fake([
            'sandbox-api.paddle.com/subscriptions/*/cancel' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    'id' => 'PADDLE-TEST-SUB-123',
                    'status' => 'canceled'
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/payments/cancel-subscription');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.'
            ]);

        // Check that order status was updated to cancellation_scheduled
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => 'cancellation_scheduled'
        ]);

        // User should still be subscribed until cancellation takes effect
        $this->user->refresh();
        $this->assertTrue($this->user->is_subscribed);
        $this->assertTrue($this->user->hasScheduledCancellation());
    }

    public function test_paddle_webhook_cancellation_processing()
    {
        // First, schedule a cancellation
        Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 'cancellation_scheduled',
            'amount' => 0,
            'transaction_id' => 'PADDLE-TEST-TXN-456',
            'metadata' => [
                'subscription_id' => 'PADDLE-TEST-SUB-123'
            ]
        ]);

        // Simulate Paddle webhook for subscription cancellation
        $webhookData = [
            'event_type' => 'subscription.cancelled',
            'data' => [
                'id' => 'PADDLE-TEST-SUB-123'
            ]
        ];

        $response = $this->postJson('/api/webhooks/paddle', $webhookData);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertArrayHasKey('status', $responseData);

        // Check that user subscription was cancelled
        $this->user->refresh();
        $this->assertFalse($this->user->is_subscribed);
        $this->assertNull($this->user->package_id);
        $this->assertNull($this->user->payment_gateway_id);
        $this->assertNull($this->user->user_license_id);

        // Check that license was deleted
        $licenseId = $this->userLicense->id;
        $this->assertDatabaseMissing('user_licences', [
            'id' => $licenseId
        ]);

        // Check that orders were updated to canceled
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => 'canceled'
        ]);

        // User should no longer have scheduled cancellation
        $this->assertFalse($this->user->hasScheduledCancellation());
        $this->assertNull($this->user->getCancellationInfo());
    }

}
