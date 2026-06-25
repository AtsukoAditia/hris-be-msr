<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftScheduleFactory extends Factory
{
    protected $model = ShiftSchedule::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'schedule_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'is_day_off' => false,
        ];
    }

    public function dayOff(): static
    {
        return $this->state(fn () => [
            'shift_id' => null,
            'is_day_off' => true,
        ]);
    }
}