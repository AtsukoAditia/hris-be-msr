<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_paid' => fake()->boolean(80),
            'requires_attachment' => fake()->boolean(30),
            'requires_balance' => fake()->boolean(70),
            'max_days_per_year' => $maxDays = fake()->numberBetween(3, 30),
            'default_days_per_year' => fake()->numberBetween(1, $maxDays),
            'max_consecutive_days' => fake()->optional()->numberBetween(1, 14),
            'min_service_months' => fake()->numberBetween(0, 12),
            'gender_restriction' => fake()->randomElement(['all', 'male', 'female']),
            'carry_forward_enabled' => fake()->boolean(30),
            'max_carry_forward_days' => fake()->optional(0.5)->numberBetween(3, 10),
            'is_active' => true,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
        ]);
    }

    public function requiresAttachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_attachment' => true,
        ]);
    }

    public function requiresBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_balance' => true,
        ]);
    }

    public function noBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_balance' => false,
        ]);
    }
}
