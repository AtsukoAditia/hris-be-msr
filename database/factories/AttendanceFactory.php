<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-1 month', 'now');
        $checkOut = (clone $checkIn)->modify('+' . rand(4, 10) . ' hours');

        return [
            'employee_id' => \App\Models\Employee::factory(),
            'shift_id' => null,
            'attendance_date' => $checkIn->format('Y-m-d'),
            'check_in_time' => $checkIn->format('Y-m-d H:i:s'),
            'check_out_time' => $checkOut->format('Y-m-d H:i:s'),
            'check_in_latitude' => -6.200000,
            'check_in_longitude' => 106.816666,
            'check_out_latitude' => -6.200000,
            'check_out_longitude' => 106.816666,
            'status' => 'present',
            'late_minutes' => 0,
            'overtime_minutes' => 0,
        ];
    }
}