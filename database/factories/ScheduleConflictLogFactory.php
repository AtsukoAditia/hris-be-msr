<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ScheduleConflictLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleConflictLogFactory extends Factory
{
    protected $model = ScheduleConflictLog::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'schedule_date' => fake()->dateTimeBetween('-7 days', '+7 days')->format('Y-m-d'),
            'conflict_type' => fake()->randomElement([
                ScheduleConflictLog::REST_HOUR,
                ScheduleConflictLog::MAX_HOURS,
                ScheduleConflictLog::OVERLAP,
                ScheduleConflictLog::COVERAGE,
            ]),
            'conflict_message' => fake()->sentence(),
            'details' => null,
        ];
    }

    public function restHour(): static
    {
        return $this->state(fn () => [
            'conflict_type' => ScheduleConflictLog::REST_HOUR,
        ]);
    }

    public function coverage(): static
    {
        return $this->state(fn () => [
            'conflict_type' => ScheduleConflictLog::COVERAGE,
        ]);
    }
}
