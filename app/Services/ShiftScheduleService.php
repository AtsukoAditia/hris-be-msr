<?php

namespace App\Services;

use App\Exceptions\BusinessValidationException;
use App\Models\Employee;
use App\Models\ScheduleConflictLog;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ShiftScheduleService
{
    public function assignShift(
        int $employeeId,
        ?int $shiftId,
        string $date,
        bool $isDayOff = false,
        ?string $notes = null,
        ?int $createdBy = null,
        ?int $existingId = null,
    ): ShiftSchedule {
        if (! $existingId) {
            $this->validateNoConflict($employeeId, $date);
        }

        $data = [
            'employee_id' => $employeeId,
            'shift_id' => $isDayOff ? null : $shiftId,
            'schedule_date' => $date,
            'is_day_off' => $isDayOff,
            'notes' => $notes,
            'created_by' => $createdBy,
        ];

        if ($existingId) {
            $schedule = ShiftSchedule::findOrFail($existingId);
            $schedule->update($data);

            return $schedule->fresh();
        }

        return ShiftSchedule::create($data);
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @param  array<int, array{shift_id?: int|null, schedule_date: string, is_day_off?: bool, notes?: string|null}>  $schedules
     * @return array{created: array, errors: array}
     */
    public function bulkAssign(array $employeeIds, array $schedules, ?int $createdBy = null): array
    {
        $created = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($employeeIds as $empIndex => $employeeId) {
                if (! Employee::find($employeeId)) {
                    $errors[] = "Employee ID {$employeeId} not found.";

                    continue;
                }

                foreach ($schedules as $schIndex => $schedule) {
                    $key = "{$empIndex}.{$schIndex}";
                    $date = $schedule['date'] ?? $schedule['schedule_date'] ?? null;

                    try {
                        $this->validateNoConflict($employeeId, $date);

                        $record = ShiftSchedule::create([
                            'employee_id' => $employeeId,
                            'shift_id' => ($schedule['is_day_off'] ?? false) ? null : ($schedule['shift_id'] ?? null),
                            'schedule_date' => $date,
                            'is_day_off' => $schedule['is_day_off'] ?? false,
                            'notes' => $schedule['notes'] ?? null,
                            'created_by' => $createdBy,
                        ]);

                        $created[] = $record;
                    } catch (BusinessValidationException $e) {
                        $errors[$key] = $e->getMessage();
                    }
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  array<int, int>|null  $employeeIds
     * @return array{created: array, errors: array}
     */
    public function copyWeek(
        string $sourceStartDate,
        string $targetStartDate,
        ?array $employeeIds = null,
        ?int $createdBy = null,
    ): array {
        $sourceStart = Carbon::parse($sourceStartDate)->startOfWeek(Carbon::MONDAY);
        $sourceEnd = $sourceStart->copy()->addDays(6);
        $targetStart = Carbon::parse($targetStartDate)->startOfWeek(Carbon::MONDAY);

        $query = ShiftSchedule::query()
            ->whereBetween('schedule_date', [$sourceStart->toDateString(), $sourceEnd->toDateString()]);

        if ($employeeIds) {
            $query->whereIn('employee_id', $employeeIds);
        }

        $sourceSchedules = $query->get();
        $created = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($sourceSchedules as $source) {
                $sourceDate = Carbon::parse($source->schedule_date)->startOfDay();
                $dayOffset = $sourceStart->copy()->startOfDay()->diffInDays($sourceDate);
                $targetDate = $targetStart->copy()->addDays($dayOffset)->toDateString();

                try {
                    $this->validateNoConflict($source->employee_id, $targetDate);

                    $newSchedule = ShiftSchedule::create([
                        'employee_id' => $source->employee_id,
                        'shift_id' => $source->shift_id,
                        'schedule_date' => $targetDate,
                        'is_day_off' => $source->is_day_off,
                        'notes' => $source->notes,
                        'created_by' => $createdBy,
                    ]);

                    $created[] = $newSchedule;
                } catch (BusinessValidationException $e) {
                    $errors[] = $e->getMessage();
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @param  array<int, int|null>  $shiftPattern  Sequence of shift IDs (null = day off)
     * @return array{created: array, errors: array}
     */
    public function assignRotating(
        array $employeeIds,
        array $shiftPattern,
        string $startDate,
        int $weeks = 4,
        ?int $createdBy = null,
    ): array {
        if (empty($shiftPattern)) {
            throw new BusinessValidationException('Shift pattern cannot be empty.');
        }

        if ($weeks < 1 || $weeks > 52) {
            throw new BusinessValidationException('Weeks must be between 1 and 52.');
        }

        $start = Carbon::parse($startDate)->startOfWeek();
        $totalDays = $weeks * 7;
        $created = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($employeeIds as $employeeId) {
                if (! Employee::find($employeeId)) {
                    $errors[] = "Employee ID {$employeeId} not found.";

                    continue;
                }

                for ($day = 0; $day < $totalDays; $day++) {
                    $date = $start->copy()->addDays($day)->toDateString();
                    $patternIndex = $day % count($shiftPattern);
                    $shiftId = $shiftPattern[$patternIndex];
                    $isDayOff = $shiftId === null;

                    try {
                        $this->validateNoConflict($employeeId, $date);

                        $created[] = ShiftSchedule::create([
                            'employee_id' => $employeeId,
                            'shift_id' => $isDayOff ? null : $shiftId,
                            'schedule_date' => $date,
                            'is_day_off' => $isDayOff,
                            'notes' => 'Auto-generated by rotating shift',
                            'created_by' => $createdBy,
                        ]);
                    } catch (BusinessValidationException $e) {
                        $errors["{$employeeId}.{$date}"] = $e->getMessage();
                    }
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function validateNoConflict(int $employeeId, string $date): void
    {
        $exists = ShiftSchedule::where('employee_id', $employeeId)
            ->whereDate('schedule_date', $date)
            ->exists();

        if ($exists) {
            throw new BusinessValidationException(
                "Employee already has a schedule for {$date}."
            );
        }
    }

    /**
     * Validate rest hours: minimum 11 hours between consecutive shifts.
     * Returns conflict logs array (empty if no conflicts).
     */
    public function validateRestHours(int $employeeId, string $startDate, string $endDate): array
    {
        $conflicts = [];
        $schedules = ShiftSchedule::with('shift')
            ->where('employee_id', $employeeId)
            ->whereBetween('schedule_date', [$startDate, $endDate])
            ->where('is_day_off', false)
            ->whereNotNull('shift_id')
            ->orderBy('schedule_date')
            ->get();

        $prevEndTime = null;
        $prevDate = null;

        foreach ($schedules as $schedule) {
            $shift = $schedule->shift;
            if (! $shift) continue;

            $dateStr = Carbon::parse($schedule->schedule_date)->toDateString();
            $startDateTime = Carbon::parse($dateStr . ' ' . $shift->start_time);
            $endDateTime = $shift->is_overnight
                ? Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay()
                : Carbon::parse($dateStr . ' ' . $shift->end_time);

            if ($prevEndTime) {
                $hoursDiff = $prevEndTime->floatDiffInHours($startDateTime);
                if ($hoursDiff < 11) {
                    $msg = "Only {$hoursDiff}h rest between {$prevDate} shift and {$schedule->schedule_date} shift (min 11h).";
                    $conflicts[] = [
                        'type' => ScheduleConflictLog::REST_HOUR,
                        'date' => $schedule->schedule_date,
                        'message' => $msg,
                        'details' => ['hours_between' => round($hoursDiff, 1), 'prev_date' => $prevDate],
                    ];
                }
            }

            $prevEndTime = $endDateTime;
            $prevDate = $schedule->schedule_date;
        }

        return $conflicts;
    }

    /**
     * Validate max hours: max 40 hours per week.
     */
    public function validateMaxHours(int $employeeId, string $startDate, string $endDate): array
    {
        $conflicts = [];
        $start = Carbon::parse($startDate)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($endDate);

        while ($start->lte($end)) {
            $weekEnd = $start->copy()->endOfWeek(Carbon::MONDAY);
            $weekStart = $start->toDateString();
            $weekEndStr = $weekEnd->toDateString();

            $schedules = ShiftSchedule::with('shift')
                ->where('employee_id', $employeeId)
                ->whereBetween('schedule_date', [$weekStart, $weekEndStr])
                ->where('is_day_off', false)
                ->whereNotNull('shift_id')
                ->get();

            $totalHours = 0;
            foreach ($schedules as $schedule) {
                $shift = $schedule->shift;
                if (! $shift) continue;

                $startTime = Carbon::parse($shift->start_time);
                $endTime = Carbon::parse($shift->end_time);
                if ($shift->is_overnight) {
                    $endTime->addDay();
                }
                $totalHours += $startTime->floatDiffInHours($endTime);
            }

            if ($totalHours > 40) {
                $msg = "Employee scheduled for {$totalHours}h in week of {$weekStart} (max 40h).";
                $conflicts[] = [
                    'type' => ScheduleConflictLog::MAX_HOURS,
                    'date' => $weekStart,
                    'message' => $msg,
                    'details' => ['total_hours' => round($totalHours, 1), 'week_start' => $weekStart],
                ];
            }

            $start->addWeek();
        }

        return $conflicts;
    }

    /**
     * Validate overlap: check for duplicate schedules on same date.
     */
    public function validateOverlap(int $employeeId, string $startDate, string $endDate): array
    {
        $conflicts = [];
        $duplicates = ShiftSchedule::where('employee_id', $employeeId)
            ->whereBetween('schedule_date', [$startDate, $endDate])
            ->selectRaw('schedule_date, COUNT(*) as cnt')
            ->groupBy('schedule_date')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $msg = "Duplicate schedule found for {$dup->schedule_date}.";
            $conflicts[] = [
                'type' => ScheduleConflictLog::OVERLAP,
                'date' => $dup->schedule_date,
                'message' => $msg,
                'details' => ['count' => $dup->cnt],
            ];
        }

        return $conflicts;
    }

    /**
     * Validate coverage: check minimum coverage per department.
     * ponytail: hardcoded min_coverage=1 per department per day. Upgrade: make configurable per department.
     */
    public function validateCoverage(string $startDate, string $endDate, int $minCoverage = 1): array
    {
        $conflicts = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Group employees by department, check each day
        $dates = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        $departments = Employee::where('is_active', true)
            ->selectRaw('department_id, COUNT(*) as employee_count')
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->get();

        foreach ($departments as $dept) {
            foreach ($dates as $date) {
                $scheduledCount = ShiftSchedule::whereHas('employee', function ($q) use ($dept) {
                    $q->where('department_id', $dept->department_id);
                })
                    ->whereDate('schedule_date', $date)
                    ->where('is_day_off', false)
                    ->count();

                if ($scheduledCount < $minCoverage) {
                    $conflicts[] = [
                        'type' => ScheduleConflictLog::COVERAGE,
                        'date' => $date,
                        'message' => "Department #{$dept->department_id} has only {$scheduledCount} scheduled employee(s) on {$date} (min {$minCoverage}).",
                        'details' => ['department_id' => $dept->department_id, 'scheduled' => $scheduledCount, 'required' => $minCoverage],
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Run all conflict validations and return combined results.
     */
    public function validateAllConflicts(string $startDate, string $endDate, ?int $employeeId = null): array
    {
        $allConflicts = [];

        if ($employeeId) {
            $allConflicts = array_merge($allConflicts, $this->validateRestHours($employeeId, $startDate, $endDate));
            $allConflicts = array_merge($allConflicts, $this->validateMaxHours($employeeId, $startDate, $endDate));
            $allConflicts = array_merge($allConflicts, $this->validateOverlap($employeeId, $startDate, $endDate));
        }

        $allConflicts = array_merge($allConflicts, $this->validateCoverage($startDate, $endDate));

        return $allConflicts;
    }

    public function publishSchedule(ShiftSchedule $schedule, int $userId): ShiftSchedule
    {
        $user = User::findOrFail($userId);
        $schedule->publish($user);

        return $schedule->fresh();
    }

    public function unpublishSchedule(ShiftSchedule $schedule): ShiftSchedule
    {
        $schedule->unpublish();

        return $schedule->fresh();
    }
}
