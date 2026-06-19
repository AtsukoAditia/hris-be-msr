<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        $opening = $this->faker->numberBetween(8, 14);

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year' => now()->year,
            'opening_days' => $opening,
            'used_days' => 0,
            'remaining_days' => $opening,
        ];
    }
}
