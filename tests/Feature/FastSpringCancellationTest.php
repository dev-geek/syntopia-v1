<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\UserLicence;
use App\Models\PaymentGateways;
use App\Services\FastSpringClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Mockery;

class FastSpringCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $package;
    protected $fastspringGateway;
    protected $userLicense;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Create test package
        $this->package = Package::create([
            'name' => 'Pro',
            'price' => 29.99,
            'fastspring_product_id' => 'pro-plan',
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        // Create FastSpring payment gateway
        $this->fastspringGateway = PaymentGateways::create([
            'name' => 'FastSpring',
            'is_active' => true,
            'config' => json_encode([
                'username' => 'test_username',
                'password' => 'test_password'
            ])
        ]);

        // Create test user with subscription
        $this->user = User::factory()->create([
            'is_subscribed' => true,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->fastspringGateway->id,
        ]);

        // Create user license
        $this->userLicense = UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'subscription_id' => 'test-subscription-123',
            'license_key' => 'TEST-LICENSE-KEY',
            'activated_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true
        ]);

        // Update user with license reference
        $this->user->update(['user_license_id' => $this->userLicense->id]);
    }

    public function test_fastspring_cancellation_schedules_end_of_billing_period()
    {
        // Create an order first so it can be updated
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'amount' => 29.99,
            'status' => 'completed',
            'transaction_id' => 'TEST-TXN-123',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        // Mock the HTTP response from FastSpring API
        Http::fake([
            'api.fastspring.com/subscriptions/*' => Http::response([
                'subscriptions' => [
                    [
                        'subscription' => 'test-subscription-123',
                        'action' => 'subscription.cancel',
                        'result' => 'success'
                    ]
                ]
            ], 200)
        ]);

        // Act as the user
        $this->actingAs($this->user);

        // Make cancellation request with JSON header
        $response = $this->postJson('/payments/cancel-subscription');

        // Assert response - check for success
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription cancellation scheduled. Your subscription will remain active until the end of your current billing period.'
        ]);

        // Verify order status was updated
        $order->refresh();
        $this->assertEquals('cancellation_scheduled', $order->status);

        // Verify user subscription is still active (not immediately cancelled)
        $this->user->refresh();
        $this->assertTrue($this->user->is_subscribed);
        $this->assertNotNull($this->user->package_id);
    }

    public function test_fastspring_cancellation_handles_api_failure()
    {
        // Mock the HTTP response from FastSpring API to return an error
        // The response should not have 'subscriptions' array with 'result' => 'success'
        Http::fake([
            'api.fastspring.com/subscriptions/*' => Http::response([
                'error' => [
                    'subscription' => 'Subscription not found'
                ]
            ], 404)
        ]);

        // Act as the user
        $this->actingAs($this->user);

        // Make cancellation request with JSON header
        $response = $this->postJson('/payments/cancel-subscription');

        // Assert response
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false
        ]);
        // The error message includes the error from FastSpring
        $response->assertJsonFragment([
            'error' => 'Failed to cancel subscription'
        ]);

        // Verify user subscription is still active
        $this->user->refresh();
        $this->assertTrue($this->user->is_subscribed);
    }

    public function test_fastspring_cancellation_requires_active_subscription()
    {
        // Deactivate user subscription
        $this->user->update(['is_subscribed' => false]);

        // Act as the user
        $this->actingAs($this->user);

        // Make cancellation request
        $response = $this->post('/payments/cancel-subscription');

        // Assert response
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No active subscription found to cancel. Please ensure you have an active subscription before attempting to cancel.');
    }

    public function test_fastspring_cancellation_requires_subscription_id()
    {
        // Remove subscription_id from license
        $this->userLicense->update(['subscription_id' => null]);

        // Act as the user
        $this->actingAs($this->user);

        // Make cancellation request
        $response = $this->post('/payments/cancel-subscription');

        // Assert response
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No subscription ID found. Please contact support.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
