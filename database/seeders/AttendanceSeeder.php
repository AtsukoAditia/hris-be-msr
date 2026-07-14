<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('is_active', true)->get();
        $shift = Shift::first();

        if (! $shift || $employees->isEmpty()) {
            return;
        }

        $today = Carbon::today();
        $startDate = $today->copy()->subMonths(3)->startOfMonth();

        // Clear existing seed data
        Attendance::query()->whereNotNull('id')->delete();

        $current = $startDate->copy();
        while ($current->lte($today)) {
            $isWeekend = in_array($current->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);

            foreach ($employees as $emp) {
                if ($isWeekend) {
                    $current->addDay();
                    continue 2;
                }

                // Random behavior patterns
                $rand = random_int(1, 100);
                if ($rand <= 5) {
                    // 5% absent
                    $status = 'absent';
                } elseif ($rand <= 20) {
                    // 15% late (with varying lateness)
                    $status = 'late';
                } else {
                    // 80% present
                    $status = 'present';
                }

                // Create shift schedule
                ShiftSchedule::updateOrCreate(
                    ['employee_id' => $emp->id, 'schedule_date' => $current->toDateString()],
                    ['shift_id' => $shift->id, 'created_by' => 1]
                );

                if ($status === 'absent') {
                    $current->addDay();
                    continue;
                }

                $baseIn = Carbon::parse($current->toDateString() . ' ' . $shift->start_time);
                $baseOut = Carbon::parse($current->toDateString() . ' ' . $shift->end_time);

                if ($shift->is_overnight && $baseOut->lte($baseIn)) {
                    $baseOut->addDay();
                }

                $checkIn = $status === 'late'
                    ? $baseIn->copy()->addMinutes(random_int(10, 45))
                    : $baseIn->copy()->subMinutes(random_int(0, 5));

                $checkOut = $baseOut->copy()->addMinutes(random_int(-5, 30));
                if ($checkOut->lte($checkIn)) {
                    $checkOut = $checkIn->copy()->addHours(8);
                }

                $lateMinutes = $status === 'late' ? (int) $baseIn->copy()->addMinutes(15)->diffInMinutes($checkIn) : 0;
                $overtimeMinutes = $checkOut->gt($baseOut) ? (int) $baseOut->diffInMinutes($checkOut) : 0;

                Attendance::create([
                    'employee_id' => $emp->id,
                    'shift_id' => $shift->id,
                    'attendance_date' => $current->toDateString(),
                    'check_in_time' => $checkIn,
                    'check_out_time' => $current->isSameDay($today) ? null : $checkOut,
                    'check_in_method' => 'default_photo_location',
                    'check_out_method' => $current->isSameDay($today) ? null : 'default_photo_location',
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'overtime_minutes' => $current->isSameDay($today) ? 0 : $overtimeMinutes,
                ]);
            }

            $current->addDay();
        }

        // Create some leave requests for realism
        $leaveDays = [
            [$employees[0]->id, $today->copy()->subDays(15), $today->copy()->subDays(13), 'Tahunan', 3],
            [$employees[2]->id, $today->copy()->subDays(10), $today->copy()->subDays(10), 'Sakit', 1],
            [$employees[3]->id, $today->copy()->subDays(8), $today->copy()->subDays(5), 'Tahunan', 4],
        ];

        foreach ($leaveDays as [$empId, $start, $end, $type, $days]) {
            Leave::updateOrCreate(
                ['employee_id' => $empId, 'start_date' => $start->toDateString()],
                [
                    'end_date' => $end->toDateString(),
                    'leave_type' => $type,
                    'total_days' => $days,
                    'status' => 'approved',
                    'reason' => "Seeder demo: cuti {$type}",
                    'approved_by' => 1,
                    'approved_at' => $start->copy()->subDay(),
                ]
            );
        }
    }
}
