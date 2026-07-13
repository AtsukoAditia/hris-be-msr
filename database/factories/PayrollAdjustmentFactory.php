<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollAdjustmentFactory extends Factory
{
    protected $model = PayrollAdjustment::class;

    public function definition(): array
    {
        return [
            'payroll_id' => Payroll::factory(),
            'type' => fake()->randomElement(['earning', 'deduction']),
            'code' => fake()->randomElement(['BONUS', 'PENALTY', 'CORRECTION', 'ALLOWANCE']),
            'name' => fake()->words(3, true),
            'amount' => fake()->numberBetween(50000, 500000),
            'reason' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function earning(): static
    {
        return $this->state(['type' => 'earning']);
    }

    public function deduction(): static
    {
        return $this->state(['type' => 'deduction']);
    }
}
