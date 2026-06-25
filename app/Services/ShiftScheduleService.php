<?php

namespace App\Services;

use App\Exceptions\BusinessValidationException;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

                    try {
                        $this->validateNoConflict($employeeId, $schedule['schedule_date']);

                        $record = ShiftSchedule::create([
                            'employee_id' => $employeeId,
                            'shift_id' => ($schedule['is_day_off'] ?? false) ? null : ($schedule['shift_id'] ?? null),
                            'schedule_date' => $schedule['schedule_date'],
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
        } catch (\Throwable $e) {
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
        $sourceStart = Carbon::parse($sourceStartDate)->startOfWeek();
        $sourceEnd = $sourceStart->copy()->endOfWeek();
        $targetStart = Carbon::parse($targetStartDate)->startOfWeek();

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
                $dayOffset = Carbon::parse($source->schedule_date)->diffInDays($sourceStart);
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
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function validateNoConflict(int $employeeId, string $date): void
    {
        $exists = ShiftSchedule::where('employee_id', $employeeId)
            ->where('schedule_date', $date)
            ->exists();

        if ($exists) {
            throw new BusinessValidationException(
                "Employee already has a schedule for {$date}."
            );
        }
    }
}