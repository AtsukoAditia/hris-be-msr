<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Shift',  // Remove space before concatenation
            'code' => fake()->unique()->bothify('SH-###'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance' => 15,
            'is_overnight' => false,
            'is_active' => true,
            'description' => null,
        ];
    }
}
