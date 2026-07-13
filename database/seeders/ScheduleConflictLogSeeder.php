<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ScheduleConflictLog;
use Illuminate\Database\Seeder;

class ScheduleConflictLogSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::inRandomOrder()->limit(5)->get();

        foreach ($employees as $employee) {
       ScheduleConflictLog::create([
        'employee_id' => $employee->id,
      'schedule_date' => now()->addDays(rand(1, 7))->format('Y-m-d'),
                'conflict_type' => fake()->randomElement([
      ScheduleConflictLog::REST_HOUR,
    ScheduleConflictLog::MAX_HOURS,
            ScheduleConflictLog::OVERLAP,
   ]),
         'conflict_message' => 'Sample conflict detected',
     'details' => null,
       ]);
        }
    }
}
