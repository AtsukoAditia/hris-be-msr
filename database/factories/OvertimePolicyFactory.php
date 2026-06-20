<?php

namespace Database\Factories;

use App\Models\OvertimePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class OvertimePolicyFactory extends Factory
{
    protected $model = OvertimePolicy::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'daily_max_minutes' => $this->faker->numberBetween(60, 480),
            'weekly_max_minutes' => $this->faker->numberBetween(300, 1200),
            'rate_multiplier' => $this->faker->randomElement([1.5, 1.75, 2.0]),
            'is_active' => true,
        ];
    }
}