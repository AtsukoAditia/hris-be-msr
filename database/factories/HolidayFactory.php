<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'date' => fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'is_recurring' => fake()->boolean(30),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
        ]);
    }
}
