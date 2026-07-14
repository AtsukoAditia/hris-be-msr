<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\Leave;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceIntelligenceService
{
    /**
     * Who's in today — live view for admin/hr/manager.
     */
    public function whoIsIn(?int $departmentId = null): array
    {
        $today = today();
        $activeEmployees = Employee::where('is_active', true)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('user')
            ->get();

        $todayAttendances = Attendance::whereDate('attendance_date', $today)
            ->whereIn('employee_id', $activeEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $todayLeaves = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereIn('employee_id', $activeEmployees->pluck('id'))
            ->pluck('employee_id');

        $scheduledToday = ShiftSchedule::whereDate('schedule_date', $today)
            ->whereIn('employee_id', $activeEmployees->pluck('id'))
            ->pluck('employee_id');

        $present = [];
        $late = [];
        $onLeave = [];
        $absent = [];
        $notScheduled = [];

        foreach ($activeEmployees as $emp) {
            $id = $emp->id;
            $attendance = $todayAttendances->get($id);

            if ($todayLeaves->contains($id)) {
                $onLeave[] = $this->formatEmployee($emp, null, 'on_leave');
            } elseif ($attendance && $attendance->check_in_time) {
                if ($attendance->status === 'late') {
                    $late[] = $this->formatEmployee($emp, $attendance, 'late');
                } else {
                    $present[] = $this->formatEmployee($emp, $attendance, 'present');
                }
            } elseif ($scheduledToday->contains($id)) {
                $absent[] = $this->formatEmployee($emp, null, 'absent');
            } else {
                $notScheduled[] = $this->formatEmployee($emp, null, 'not_scheduled');
            }
        }

        return [
            'date' => $today->format('Y-m-d'),
            'total_active' => $activeEmployees->count(),
            'summary' => [
                'present' => count($present),
                'late' => count($late),
                'on_leave' => count($onLeave),
                'absent' => count($absent),
                'not_scheduled' => count($notScheduled),
            ],
            'present' => $present,
            'late' => $late,
            'on_leave' => $onLeave,
            'absent' => $absent,
            'not_scheduled' => $notScheduled,
        ];
    }

    /**
     * Monthly attendance summary per employee.
     */
    public function monthlySummary(int $year, int $month, ?int $departmentId = null): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $workingDays = $this->countWorkingDays($start, $end);

        $employees = Employee::where('is_active', true)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('user')
            ->get();

        $attendances = Attendance::whereBetween('attendance_date', [$start, $end])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $leaves = Leave::where('status', 'approved')
            ->whereBetween('start_date', [$start, $end])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $summaries = $employees->map(function ($emp) use ($attendances, $leaves, $workingDays) {
            $empAttendances = $attendances->get($emp->id, collect());
            $empLeaves = $leaves->get($emp->id, collect());

            $present = $empAttendances->where('status', 'present')->count();
            $late = $empAttendances->where('status', 'late')->count();
            $absent = max(0, $workingDays - $present - $late - $empLeaves->sum('total_days'));
            $totalLateMinutes = (int) $empAttendances->sum('late_minutes');
            $totalOvertimeMinutes = (int) $empAttendances->sum('overtime_minutes');
            $attendanceRate = $workingDays > 0 ? round(($present + $late) / $workingDays * 100, 1) : 0;

            return [
                'employee_id' => $emp->id,
                'name' => $emp->user?->name,
                'department' => $emp->department,
                'position' => $emp->position,
                'working_days' => $workingDays,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'on_leave' => $empLeaves->sum('total_days'),
                'total_late_minutes' => $totalLateMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'attendance_rate' => $attendanceRate,
            ];
        });

        return [
            'period' => ['year' => $year, 'month' => $month],
            'working_days' => $workingDays,
            'summaries' => $summaries->values(),
        ];
    }

    /**
     * Detect attendance anomalies — patterns that need attention.
     */
    public function anomalies(?int $months = 3): array
    {
        $start = now()->subMonths($months)->startOfMonth();
        $end = now()->endOfMonth();

        $employees = Employee::where('is_active', true)->with('user')->get();
        $results = [];

        foreach ($employees as $emp) {
            $attendances = Attendance::where('employee_id', $emp->id)
                ->whereBetween('attendance_date', [$start, $end])
                ->orderBy('attendance_date')
                ->get();

            if ($attendances->isEmpty()) {
                continue;
            }

            $flags = [];

            // 1. Consecutive lates (3+)
            $consecutiveLates = $this->maxConsecutiveLates($attendances);
            if ($consecutiveLates >= 3) {
                $flags[] = [
                    'type' => 'consecutive_lates',
                    'severity' => $consecutiveLates >= 5 ? 'high' : 'medium',
                    'message' => "Terlambat {$consecutiveLates}x berturut-turut",
                    'value' => $consecutiveLates,
                ];
            }

            // 2. Frequent Monday/Friday absences
            $mondayAbsences = $this->countDayAbsences($attendances, 1);
            $fridayAbsences = $this->countDayAbsences($attendances, 5);
            if ($mondayAbsences >= 3) {
                $flags[] = [
                    'type' => 'monday_absences',
                    'severity' => 'medium',
                    'message' => "Absen Senin {$mondayAbsences}x dalam {$months} bulan",
                    'value' => $mondayAbsences,
                ];
            }
            if ($fridayAbsences >= 3) {
                $flags[] = [
                    'type' => 'friday_absences',
                    'severity' => 'medium',
                    'message' => "Absen Jumat {$fridayAbsences}x dalam {$months} bulan",
                    'value' => $fridayAbsences,
                ];
            }

            // 3. Excessive overtime (> 600 min/month avg)
            $totalOT = (int) $attendances->sum('overtime_minutes');
            $avgOTPerMonth = $months > 0 ? $totalOT / $months : 0;
            if ($avgOTPerMonth > 600) {
                $flags[] = [
                    'type' => 'excessive_overtime',
                    'severity' => 'high',
                    'message' => "Rata-rata lembur {$this->formatMinutes((int) $avgOTPerMonth)}/bulan",
                    'value' => (int) $avgOTPerMonth,
                ];
            }

            // 4. High late rate (> 30%)
            $totalDays = $attendances->count();
            $lateDays = $attendances->where('status', 'late')->count();
            $lateRate = $totalDays > 0 ? round($lateDays / $totalDays * 100) : 0;
            if ($lateRate > 30 && $totalDays >= 5) {
                $flags[] = [
                    'type' => 'high_late_rate',
                    'severity' => $lateRate > 50 ? 'high' : 'medium',
                    'message' => "Tingkat keterlambatan {$lateRate}%",
                    'value' => $lateRate,
                ];
            }

            if (! empty($flags)) {
                $results[] = [
                    'employee_id' => $emp->id,
                    'name' => $emp->user?->name,
                    'department' => $emp->department,
                    'flags' => $flags,
                ];
            }
        }

        // Sort: high severity first
        usort($results, function ($a, $b) {
            $sev = ['high' => 0, 'medium' => 1, 'low' => 2];
            $maxA = collect($a['flags'])->min(fn ($f) => $sev[$f['severity']] ?? 3);
            $maxB = collect($b['flags'])->min(fn ($f) => $sev[$f['severity']] ?? 3);
            return $maxA <=> $maxB;
        });

        return [
            'period_months' => $months,
            'total_flagged' => count($results),
            'employees' => $results,
        ];
    }

    /**
     * Attendance trend — daily aggregates for the last N days.
     */
    public function trend(int $days = 30, ?int $departmentId = null): array
    {
        $start = now()->subDays($days)->startOfDay();
        $end = now()->endOfDay();

        $attendances = Attendance::whereBetween('attendance_date', [$start, $end])
            ->when($departmentId, function ($q) use ($departmentId) {
                $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId));
            })
            ->get()
            ->groupBy(fn ($a) => $a->attendance_date->format('Y-m-d'));

        $trend = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayAttendances = $attendances->get($date, collect());
            $total = $dayAttendances->count();

            $trend[] = [
                'date' => $date,
                'total' => $total,
                'present' => $dayAttendances->where('status', 'present')->count(),
                'late' => $dayAttendances->where('status', 'late')->count(),
                'absent' => max(0, (Employee::where('is_active', true)->count()) - $total),
                'avg_late_minutes' => $total > 0 ? round($dayAttendances->avg('late_minutes'), 1) : 0,
            ];
        }

        return ['days' => $days, 'trend' => $trend];
    }

    // --- Helpers ---

    private function formatEmployee(Employee $emp, ?Attendance $attendance, string $status): array
    {
        return [
            'employee_id' => $emp->id,
            'name' => $emp->user?->name,
            'department' => $emp->department,
            'position' => $emp->position,
            'status' => $status,
            'check_in_time' => $attendance ? optional($attendance->check_in_time)->format('H:i:s') : null,
            'check_out_time' => $attendance ? optional($attendance->check_out_time)->format('H:i:s') : null,
            'late_minutes' => $attendance?->late_minutes ?? 0,
        ];
    }

    private function countWorkingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (! in_array($current->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $days++;
            }
            $current->addDay();
        }
        return $days;
    }

    private function maxConsecutiveLates(Collection $attendances): int
    {
        $max = 0;
        $current = 0;
        foreach ($attendances as $a) {
            if ($a->status === 'late') {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }
        return $max;
    }

    private function countDayAbsences(Collection $attendances, int $dayOfWeek): int
    {
        return $attendances->filter(function ($a) use ($dayOfWeek) {
            return $a->attendance_date->dayOfWeek === $dayOfWeek && $a->status === 'absent';
        })->count();
    }

    private function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
    }
}
