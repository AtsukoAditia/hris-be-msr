<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    public function definition(): array
    {
        return [
            'leave_type_id' => LeaveType::factory(),
            'year' => (int) date('Y'),
            'policy_name' => fake()->unique()->word().' Policy',
            'default_quota' => fake()->randomElement([10, 12, 14]),
            'min_service_months' => 0,
            'accrual_type' => fake()->randomElement(['yearly', 'monthly']),
            'accrual_amount' => fake()->numberBetween(1, 12),
            'max_carry_forward_days' => fake()->randomElement([null, 5, 10]),
            'carry_forward_expiry_month' => null,
            'carry_forward_expiry_day' => null,
            'carry_forward_expiry_months' => null,
            'is_active' => true,
        ];
    }
}
