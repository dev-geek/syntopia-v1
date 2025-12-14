<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Models\License;
use App\Services\License\LicenseApiService;
use App\Services\Payment\Gateways\FastSpringPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;

class FastSpringUpgradeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $starterPackage;
    protected $proPackage;
    protected $fastspringGateway;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test packages
        $this->starterPackage = Package::create([
            'name' => 'Starter',
            'price' => 9.99,
            'duration' => 'monthly',
            'features' => ['feature1', 'feature2'],
            'fastspring_product_id' => 'starter-plan'
        ]);

        $this->proPackage = Package::create([
            'name' => 'Pro',
            'price' => 19.99,
            'duration' => 'monthly',
            'features' => ['feature1', 'feature2', 'feature3'],
            'fastspring_product_id' => 'pro-plan'
        ]);

        // Create payment gateway
        $this->fastspringGateway = PaymentGateways::create([
            'name' => 'FastSpring',
            'is_active' => true
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_subscribed' => true,
            'subscription_id' => 'test-subscription-123',
            'payment_gateway_id' => $this->fastspringGateway->id,
            'package_id' => $this->starterPackage->id,
            'license_key' => 'old-license-key'
        ]);
    }

    public function test_fastspring_upgrade_creates_new_license()
    {
        // Mock the LicenseApiService
        $licenseApiService = Mockery::mock(LicenseApiService::class);
        $licenseApiService->shouldReceive('makeLicense')
            ->once()
            ->andReturn('new-license-key-123');
        $licenseApiService->shouldReceive('addLicenseToTenant')
            ->once()
            ->andReturn(true);

        // Mock the FastSpringPaymentGateway
        $fastspringGateway = Mockery::mock(FastSpringPaymentGateway::class);
        $fastspringGateway->shouldReceive('upgradeSubscription')
            ->once()
            ->andReturn([
                'subscriptions' => [
                    [
                        'subscription' => 'test-subscription-123',
                        'result' => 'success'
                    ]
                ]
            ]);

        // Mock PaymentGatewayFactory to return the mocked gateway
        $paymentGatewayFactory = Mockery::mock(\App\Factories\PaymentGatewayFactory::class);
        $paymentGatewayFactory->shouldReceive('create')
            ->with('FastSpring')
            ->once()
            ->andReturn($fastspringGateway);

        // Bind the mocked services
        $this->app->instance(LicenseApiService::class, $licenseApiService);
        $this->app->instance(\App\Factories\PaymentGatewayFactory::class, $paymentGatewayFactory);

        // Make the upgrade request
        $response = $this->actingAs($this->user)
            ->postJson('/api/payments/upgrade/pro', [
                'package' => 'pro'
            ]);

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Upgrade completed successfully',
                'package' => 'Pro'
            ]);

        // Assert the license was created
        $this->assertDatabaseHas('user_licences', [
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'license_key' => 'new-license-key-123',
            'is_active' => true,
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);

        // Assert the user was updated
        $this->user->refresh();
        $this->assertEquals($this->proPackage->id, $this->user->package_id);
        $this->assertNotNull($this->user->user_license_id);

        // Assert only one license is active
        $activeLicenses = \App\Models\UserLicence::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(1, $activeLicenses);
    }

    public function test_fastspring_upgrade_fails_without_subscription()
    {
        // Remove subscription from user
        $this->user->update([
            'is_subscribed' => false,
            'subscription_id' => null
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/payments/upgrade/pro', [
                'package' => 'pro'
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No active subscription to upgrade'
            ]);
    }

    public function test_fastspring_upgrade_fails_with_invalid_package()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/payments/upgrade/invalid-package', [
                'package' => 'invalid-package'
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid package selected'
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
