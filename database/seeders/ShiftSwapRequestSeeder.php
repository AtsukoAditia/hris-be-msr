<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use Illuminate\Database\Seeder;

class ShiftSwapRequestSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::inRandomOrder()->limit(10)->get();

        if ($employees->count() < 2) {
            return;
        }

        foreach ($employees as $index => $requester) {
            $target = $employees->where('id', '!=', $requester->id)->random();

            $requesterSchedule = ShiftSchedule::where('employee_id', $requester->id)
                ->whereDate('schedule_date', '>=', now())
                ->first();

            $targetSchedule = ShiftSchedule::where('employee_id', $target->id)
                ->whereDate('schedule_date', '>=', now())
                ->first();

            if ($requesterSchedule && $targetSchedule) {
                ShiftSwapRequest::create([
                    'requester_id' => $requester->id,
                    'target_id' => $target->id,
                    'requester_schedule_id' => $requesterSchedule->id,
                    'target_schedule_id' => $targetSchedule->id,
                    'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
                    'reason' => fake()->sentence(),
                ]);
            }

            if ($index >= 4) {
                break;
            }
        }
    }
}
