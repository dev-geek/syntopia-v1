<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\PaymentGateways;
use App\Models\UserLicence;
use App\Services\LicenseService;
use App\Services\LicenseApiService;
use App\Services\FastSpringClient;
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
            'payment_gateway_id' => $this->fastspringGateway->id,
            'package_id' => $this->starterPackage->id,
            'status' => 1,
            'email_verified_at' => now()
        ]);

        // Create user license with subscription_id (required for upgrades)
        $userLicense = UserLicence::create([
            'user_id' => $this->user->id,
            'package_id' => $this->starterPackage->id,
            'payment_gateway_id' => $this->fastspringGateway->id,
            'subscription_id' => 'FS-test-subscription-123',
            'license_key' => 'old-license-key',
            'is_active' => true,
            'activated_at' => now()
        ]);

        $this->user->update(['user_license_id' => $userLicense->id]);
    }

    public function test_fastspring_upgrade_creates_new_license()
    {
        // FastSpring upgrade creates a checkout URL, not an immediate upgrade
        // Make the upgrade request
        $response = $this->actingAs($this->user)
            ->postJson('/api/payments/upgrade/pro', [
                'package' => 'pro'
            ]);

        // Assert the response - FastSpring returns a checkout URL
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'checkout_url',
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);
        
        $responseData = $response->json();
        $this->assertNotEmpty($responseData['checkout_url']);
        $this->assertIsString($responseData['checkout_url']);

        // Assert an order was created for the upgrade
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'order_type' => 'upgrade',
            'status' => 'pending',
            'payment_gateway_id' => $this->fastspringGateway->id
        ]);
    }

    public function test_fastspring_upgrade_fails_without_subscription()
    {
        // Remove subscription from user and license
        $this->user->update([
            'is_subscribed' => false
        ]);
        
        // Remove subscription_id from license
        $userLicense = UserLicence::where('user_id', $this->user->id)->first();
        if ($userLicense) {
            $userLicense->update(['subscription_id' => null]);
        }

        $response = $this->actingAs($this->user)
            ->postJson('/api/payments/upgrade/pro', [
                'package' => 'pro'
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Subscription Required'
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
                'error' => 'Invalid Package'
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
