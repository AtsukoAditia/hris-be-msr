<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'status' => Payroll::STATUS_DRAFT,
            'currency' => 'IDR',
            'basic_salary' => fake()->randomFloat(2, 5000000, 20000000),
            'total_earnings' => fake()->randomFloat(2, 5000000, 25000000),
            'total_deductions' => fake()->randomFloat(2, 0, 5000000),
            'net_salary' => fake()->randomFloat(2, 5000000, 25000000),
            'attendance_days' => fake()->numberBetween(20, 26),
            'absent_days' => fake()->numberBetween(0, 3),
            'late_minutes' => fake()->numberBetween(0, 60),
            'unpaid_leave_days' => 0,
            'overtime_minutes' => fake()->numberBetween(0, 240),
            'generated_by' => null,
            'generated_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Payroll::STATUS_DRAFT]);
    }

    public function submitted(): static
    {
        return $this->state(['status' => Payroll::STATUS_SUBMITTED]);
    }

    public function finalized(): static
    {
        return $this->state(['status' => Payroll::STATUS_FINALIZED]);
    }
}
