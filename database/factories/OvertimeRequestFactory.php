<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class OvertimeRequestFactory extends Factory
{
    protected $model = OvertimeRequest::class;

    public function definition(): array
    {
        $startTime = $this->faker->time('H:i', '18:00');
        $startMinutes = (int) substr($startTime, 0, 2) * 60 + (int) substr($startTime, 3, 2);
        $plannedMinutes = $this->faker->numberBetween(60, 240);
        $endMinutes = $startMinutes + $plannedMinutes;
        $endTime = sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);

        return [
            'employee_id' => Employee::factory(),
            'overtime_policy_id' => OvertimePolicy::factory(),
            'overtime_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'planned_start_time' => $startTime,
            'planned_end_time' => $endTime,
            'planned_minutes' => $plannedMinutes,
            'status' => 'pending',
            'reason' => $this->faker->sentence(),
        ];
    }
}
