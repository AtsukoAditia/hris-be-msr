<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftSwapRequestFactory extends Factory
{
    protected $model = ShiftSwapRequest::class;

    public function definition(): array
    {
        return [
            'requester_id' => Employee::factory(),
            'target_id' => Employee::factory(),
            'requester_schedule_id' => ShiftSchedule::factory(),
            'target_schedule_id' => null,
            'status' => ShiftSwapRequest::STATUS_PENDING,
            'reason' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ShiftSwapRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ShiftSwapRequest::STATUS_CANCELLED,
        ]);
    }
}
