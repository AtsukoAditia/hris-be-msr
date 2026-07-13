<?php

namespace Database\Factories;

use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftScheduleVersionFactory extends Factory
{
    protected $model = ShiftScheduleVersion::class;

    public function definition(): array
    {
        return [
            'shift_schedule_id' => ShiftSchedule::factory(),
            'version' => 1,
            'changes' => ['field' => 'shift_id', 'old' => null, 'new' => 1],
            'changed_by' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'published', 'unpublished']),
            'notes' => null,
        ];
    }
}
