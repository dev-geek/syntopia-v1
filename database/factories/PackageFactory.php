<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'price' => $this->faker->randomFloat(2, 0, 100),
            'duration' => $this->faker->randomElement(['monthly', 'yearly', 'lifetime']),
            'features' => json_encode(['feature1', 'feature2']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a free package
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'price' => 0,
        ]);
    }

    /**
     * Create a paid package
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->words(2, true),
            'price' => $this->faker->randomFloat(2, 10, 100),
        ]);
    }
}
