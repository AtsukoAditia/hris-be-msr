<?php

namespace App\Services;

use App\Exceptions\BusinessValidationException;
use App\Models\Employee;
use App\Models\ShiftSchedule;
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
}
