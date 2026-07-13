<?php

namespace App\Services;

use App\Exceptions\BusinessValidationException;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShiftSwapService
{
    public function createSwapRequest(
        int $requesterId,
        int $targetId,
        int $requesterScheduleId,
        ?int $targetScheduleId,
        ?string $reason = null,
    ): ShiftSwapRequest {
        if ($requesterId === $targetId) {
            throw new BusinessValidationException('Cannot swap with yourself.');
        }

        $requesterSchedule = ShiftSchedule::findOrFail($requesterScheduleId);

        if ($requesterSchedule->employee_id !== $requesterId) {
            throw new BusinessValidationException('Requester schedule does not belong to requester.');
        }

        if ($requesterSchedule->status === ShiftSchedule::STATUS_PUBLISHED) {
            throw new BusinessValidationException('Cannot swap published schedules. Unpublish first.');
        }

        if ($targetScheduleId) {
            $targetSchedule = ShiftSchedule::findOrFail($targetScheduleId);

            if ($targetSchedule->employee_id !== $targetId) {
                throw new BusinessValidationException('Target schedule does not belong to target employee.');
            }

            if ($targetSchedule->status === ShiftSchedule::STATUS_PUBLISHED) {
                throw new BusinessValidationException('Cannot swap published schedules. Unpublish first.');
            }
        }

        return ShiftSwapRequest::create([
            'requester_id' => $requesterId,
            'target_id' => $targetId,
            'requester_schedule_id' => $requesterScheduleId,
            'target_schedule_id' => $targetScheduleId,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
        ]);
    }

    public function approveSwap(ShiftSwapRequest $swapRequest, User $reviewer, ?string $notes = null): ShiftSwapRequest
    {
        if ($swapRequest->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new BusinessValidationException('Swap request is not pending.');
        }

        DB::beginTransaction();

        try {
            $swapRequest->approve($reviewer, $notes);

            // Execute the actual swap
            $requesterSchedule = ShiftSchedule::find($swapRequest->requester_schedule_id);
            $targetSchedule = ShiftSchedule::find($swapRequest->target_schedule_id);

            if ($requesterSchedule && $targetSchedule) {
                $requesterShift = $requesterSchedule->shift_id;
                $requesterDayOff = $requesterSchedule->is_day_off;

                $requesterSchedule->update([
                    'shift_id' => $targetSchedule->shift_id,
                    'is_day_off' => $targetSchedule->is_day_off,
                ]);

                $targetSchedule->update([
                    'shift_id' => $requesterShift,
                    'is_day_off' => $requesterDayOff,
                ]);
            }

            DB::commit();

            return $swapRequest->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function rejectSwap(ShiftSwapRequest $swapRequest, User $reviewer, ?string $reason = null): ShiftSwapRequest
    {
        if ($swapRequest->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new BusinessValidationException('Swap request is not pending.');
        }

        $swapRequest->reject($reviewer, $reason);

        return $swapRequest->fresh();
    }

    public function cancelSwap(ShiftSwapRequest $swapRequest, int $userId): ShiftSwapRequest
    {
        if ($swapRequest->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new BusinessValidationException('Swap request is not pending.');
        }

        $employeeId = User::find($userId)?->employee?->id;

        if ($swapRequest->requester_id !== $employeeId) {
            throw new BusinessValidationException('Only the requester can cancel a swap request.');
        }

        $swapRequest->update([
            'status' => ShiftSwapRequest::STATUS_CANCELLED,
        ]);

        return $swapRequest->fresh();
    }
}
