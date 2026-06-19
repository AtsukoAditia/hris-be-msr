<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceCorrectionRequestFactory extends Factory
{
    protected $model = AttendanceCorrectionRequest::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();
        $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);
        $date = $this->faker->dateTimeBetween('-30 days', '-1 day');

        return [
            'employee_id' => $employee->id,
            'attendance_id' => $attendance->id,
            'correction_date' => $date->format('Y-m-d'),
            'correction_type' => $this->faker->randomElement(['check_in', 'check_out', 'both']),
            'requested_check_in' => now()->setTime(8, 0),
            'requested_check_out' => now()->setTime(17, 0),
            'original_check_in' => now()->setTime(8, 30),
            'original_check_out' => now()->setTime(16, 30),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'rejected']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}