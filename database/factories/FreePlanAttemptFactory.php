<?php

namespace Database\Factories;

use App\Models\FreePlanAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FreePlanAttempt>
 */
class FreePlanAttemptFactory extends Factory
{
    protected $model = FreePlanAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'device_fingerprint' => $this->faker->sha256(),
            'fingerprint_id' => $this->faker->uuid(),
            'data' => json_encode(['test' => 'data']),
            'email' => $this->faker->email(),
            'is_blocked' => false,
            'blocked_at' => null,
            'block_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a blocked attempt
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => 'Test block',
        ]);
    }

    /**
     * Create an attempt with specific IP
     */
    public function withIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
        ]);
    }

    /**
     * Create an attempt with specific email
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }
}
