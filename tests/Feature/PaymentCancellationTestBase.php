<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\UserLicence;
use App\Models\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

abstract class PaymentCancellationTestBase extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $package;
    protected $gateway;
    protected $userLicense;

    abstract protected function getGatewayName(): string;
    abstract protected function getSubscriptionId(): string;
    abstract protected function getPackagePrice(): float;

    protected function setUp(): void
    {
        parent::setUp();

        $this->package = Package::create([
            'name' => 'Pro',
            'price' => $this->getPackagePrice(),
            'features' => json_encode(['feature1', 'feature2'])
        ]);

        $this->gateway = PaymentGateways::create([
            'name' => $this->getGatewayName(),
            'is_active' => true
        ]);

        $this->user = User::factory()->create([
            'is_subscribed' => true,
            'package_id' => $this->package->id,
            'payment_gateway_id' => $this->gateway->id,
        ]);

        $this->userLicense = UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'subscription_id' => $this->getSubscriptionId(),
            'license_key' => 'TEST-LICENSE-KEY',
            'activated_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true
        ]);

        $this->user->update(['user_license_id' => $this->userLicense->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancellation_schedules_end_of_billing_period()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/cancel-subscription');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cancellation_type' => 'end_of_billing_period',
            ]);

        $this->user->refresh();
        $this->assertTrue($this->user->is_subscribed);
        $this->assertNotNull($this->user->package_id);
    }

    public function test_cancellation_requires_active_subscription()
    {
        $this->user->update(['is_subscribed' => false]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/cancel-subscription');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cancellation_requires_subscription_id()
    {
        $this->userLicense->update(['subscription_id' => null]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/cancel-subscription');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }
}

