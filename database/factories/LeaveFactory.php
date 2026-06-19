<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $start = now()->addDays(rand(1, 30));
        $end = $start->copy()->addDays(rand(0, 4));

        $leaveType = LeaveType::factory()->create(['is_active' => true]);

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => $leaveType->id,
            'leave_type' => $leaveType->code,
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => $start->diffInDays($end) + 1,
            'reason' => fake()->sentence(),
            'status' => Leave::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn () => ['status' => Leave::STATUS_PENDING]);
    }

    public function approved(?User $approver = null): self
    {
        return $this->state(fn () => [
            'status' => Leave::STATUS_APPROVED,
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => Leave::STATUS_REJECTED,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => ['status' => Leave::STATUS_CANCELLED]);
    }
}
