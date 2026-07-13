<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
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
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'finalized_by' => null,
            'finalized_at' => null,
            'paid_by' => null,
            'paid_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Payroll::STATUS_DRAFT]);
    }

    public function submitted(): static
    {
        return $this->state([
            'status' => Payroll::STATUS_SUBMITTED,
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ]);
    }

    public function reviewed(): static
    {
        return $this->state([
            'status' => Payroll::STATUS_REVIEWED,
            'submitted_by' => User::factory(),
            'submitted_at' => now()->subHour(),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => Payroll::STATUS_APPROVED,
            'submitted_by' => User::factory(),
            'submitted_at' => now()->subHours(2),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHour(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function finalized(): static
    {
        return $this->state([
            'status' => Payroll::STATUS_FINALIZED,
            'submitted_by' => User::factory(),
            'submitted_at' => now()->subHours(3),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(2),
            'approved_by' => User::factory(),
            'approved_at' => now()->subHour(),
            'finalized_by' => User::factory(),
            'finalized_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status' => Payroll::STATUS_PAID,
            'submitted_by' => User::factory(),
            'submitted_at' => now()->subHours(4),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(3),
            'approved_by' => User::factory(),
            'approved_at' => now()->subHours(2),
            'finalized_by' => User::factory(),
            'finalized_at' => now()->subHour(),
            'paid_by' => User::factory(),
            'paid_at' => now(),
        ]);
    }
}
