<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'now');
        $end = (clone $start)->modify('+1 month -1 day');

        return [
            'name' => fake()->randomElement(['January 2026', 'February 2026', 'March 2026']).' Payroll',
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'cutoff_start_date' => $start->format('Y-m-d'),
            'cutoff_end_date' => $end->format('Y-m-d'),
            'status' => 'open',
        ];
    }
}
