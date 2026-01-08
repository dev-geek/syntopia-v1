<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\WithFaker;

class PaddleCancellationTest extends PaymentCancellationTestBase
{
    use WithFaker;

    protected Order $order;

    protected function getGatewayName(): string
    {
        return 'Paddle';
    }

    protected function getSubscriptionId(): string
    {
        return 'PADDLE-TEST-SUB-123';
    }

    protected function getPackagePrice(): float
    {
        return 29.99;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->gateway->id,
            'status' => 'completed',
            'transaction_id' => 'PADDLE-TEST-TXN-123',
            'metadata' => [
                'subscription_id' => $this->getSubscriptionId()
            ]
        ]);
    }

    public function test_user_has_scheduled_cancellation()
    {
        Order::factory()->create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->gateway->id,
            'status' => 'cancellation_scheduled',
            'transaction_id' => 'PADDLE-TEST-TXN-456',
            'metadata' => [
                'subscription_id' => $this->getSubscriptionId()
            ]
        ]);

        $this->assertTrue($this->user->hasScheduledCancellation());

        $cancellationInfo = $this->user->getCancellationInfo();
        $this->assertNotNull($cancellationInfo);
        $this->assertEquals('Paddle', $cancellationInfo['gateway']);
        $this->assertEquals('Pro', $cancellationInfo['package']);
    }

    public function test_user_does_not_have_scheduled_cancellation()
    {
        $this->assertFalse($this->user->hasScheduledCancellation());
        $this->assertNull($this->user->getCancellationInfo());
    }

    public function test_subscription_status_includes_cancellation_info()
    {
        Order::factory()->create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->gateway->id,
            'status' => 'cancellation_scheduled',
            'transaction_id' => 'PADDLE-TEST-TXN-456',
            'metadata' => [
                'subscription_id' => $this->getSubscriptionId()
            ]
        ]);

        $status = $this->user->subscription_status;

        $this->assertTrue($status['is_active']);
        $this->assertTrue($status['has_scheduled_cancellation']);
        $this->assertEquals('Pro', $status['package_name']);
        $this->assertEquals('Paddle', $status['gateway']);
    }

    public function test_paddle_webhook_cancellation_processing()
    {
        // First, schedule a cancellation
        Order::factory()->create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'status' => 'cancellation_scheduled',
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

        $response->assertStatus(200)
            ->assertJson(['status' => 'processed']);

        // Check that user subscription was cancelled
        $this->user->refresh();
        $this->assertFalse($this->user->is_subscribed);
        $this->assertNull($this->user->subscription_id);
        $this->assertNull($this->user->package_id);
        $this->assertNull($this->user->payment_gateway_id);
        $this->assertNull($this->user->user_license_id);

        // Check that license was deleted
        $this->assertDatabaseMissing('user_licences', [
            'id' => $this->userLicense->id
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
