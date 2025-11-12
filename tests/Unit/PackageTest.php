<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function isFree_returns_true_for_package_named_free_lowercase()
    {
        $package = Package::factory()->create([
            'name' => 'free',
            'price' => 100
        ]);

        $this->assertTrue($package->isFree());
    }

    /** @test */
    public function isFree_returns_true_for_package_named_free_uppercase()
    {
        $package = Package::factory()->create([
            'name' => 'FREE',
            'price' => 100
        ]);

        $this->assertTrue($package->isFree());
    }

    /** @test */
    public function isFree_returns_true_for_package_named_free_mixed_case()
    {
        $package = Package::factory()->create([
            'name' => 'FrEe',
            'price' => 100
        ]);

        $this->assertTrue($package->isFree());
    }

    /** @test */
    public function isFree_returns_true_for_package_with_zero_price()
    {
        $package = Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        $this->assertTrue($package->isFree());
    }

    /** @test */
    public function isFree_returns_false_for_paid_package()
    {
        $package = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $this->assertFalse($package->isFree());
    }

    /** @test */
    public function getEffectivePrice_returns_zero_for_free_package_by_name()
    {
        $package = Package::factory()->create([
            'name' => 'Free',
            'price' => 50
        ]);

        $this->assertEquals(0, $package->getEffectivePrice());
    }

    /** @test */
    public function getEffectivePrice_returns_zero_for_free_package_by_price()
    {
        $package = Package::factory()->create([
            'name' => 'Starter',
            'price' => 0
        ]);

        $this->assertEquals(0, $package->getEffectivePrice());
    }

    /** @test */
    public function getEffectivePrice_returns_actual_price_for_paid_package()
    {
        $package = Package::factory()->create([
            'name' => 'Pro',
            'price' => 99.99
        ]);

        $this->assertEquals(99.99, $package->getEffectivePrice());
    }

    /** @test */
    public function getEffectivePrice_returns_zero_for_free_package_case_insensitive()
    {
        $package = Package::factory()->create([
            'name' => 'free',
            'price' => 100
        ]);

        $this->assertEquals(0, $package->getEffectivePrice());
    }

    /** @test */
    public function getEffectivePrice_handles_decimal_prices_correctly()
    {
        $package = Package::factory()->create([
            'name' => 'Business',
            'price' => 149.50
        ]);

        $this->assertEquals(149.50, $package->getEffectivePrice());
    }
}

