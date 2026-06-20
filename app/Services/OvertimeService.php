<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    public function list(array $filters, User $actor): LengthAwarePaginator
    {
        $query = OvertimeRequest::query()
            ->with(['employee.user', 'overtimePolicy', 'approver'])
            ->latest('date');

        if ($actor->role === 'employee') {
            $query->where('employee_id', $actor->employee->id);
        } elseif ($actor->role === 'manager') {
            $subordinateIds = Employee::where('manager_id', $actor->employee->id)->pluck('id');
            $query->whereIn('employee_id', $subordinateIds);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function submit(array $data, User $actor): OvertimeRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $plannedMinutes = $this->calculateMinutes(
                $data['start_time'],
                $data['end_time']
            );

            $policy = \App\Models\OvertimePolicy::findOrFail($data['overtime_policy_id']);

            return OvertimeRequest::create([
                'employee_id'        => $actor->employee->id,
                'overtime_policy_id' => $data['overtime_policy_id'],
                'date'               => $data['date'],
                'start_time'         => $data['start_time'],
                'end_time'           => $data['end_time'],
                'planned_minutes'    => $plannedMinutes,
                'rate_multiplier'    => $policy->rate_multiplier,
                'status'             => OvertimeRequest::STATUS_PENDING,
                'reason'             => $data['reason'],
            ]);
        });
    }

    public function cancel(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        if (!$overtimeRequest->isPending()) {
            throw new \DomainException('Only pending overtime requests can be cancelled.');
        }

        $overtimeRequest->update(['status' => OvertimeRequest::STATUS_CANCELLED]);

        return $overtimeRequest->fresh();
    }

    public function approve(OvertimeRequest $overtimeRequest, User $actor): OvertimeRequest
    {
        if (!$overtimeRequest->isPending()) {
            throw new \DomainException('Only pending overtime requests can be approved.');
        }

        return DB::transaction(function () use ($overtimeRequest, $actor) {
            $overtimeRequest->update([
                'status'      => OvertimeRequest::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $overtimeRequest->fresh(['employee.user', 'overtimePolicy', 'approver']);
        });
    }

    public function reject(OvertimeRequest $overtimeRequest, User $actor, string $rejectionReason): OvertimeRequest
    {
        if (!$overtimeRequest->isPending()) {
            throw new \DomainException('Only pending overtime requests can be rejected.');
        }

        $overtimeRequest->update([
            'status'           => OvertimeRequest::STATUS_REJECTED,
            'approved_by'      => $actor->id,
            'approved_at'      => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        return $overtimeRequest->fresh();
    }

    public function recordActualMinutes(OvertimeRequest $overtimeRequest, int $actualMinutes): OvertimeRequest
    {
        if ($overtimeRequest->status !== OvertimeRequest::STATUS_APPROVED) {
            throw new \DomainException('Actual minutes can only be recorded for approved requests.');
        }

        $overtimeRequest->update(['actual_minutes' => $actualMinutes]);

        return $overtimeRequest->fresh();
    }

    private function calculateMinutes(string $startTime, string $endTime): int
    {
        [$startH, $startM] = explode(':', $startTime);
        [$endH, $endM] = explode(':', $endTime);

        $startMinutes = (int) $startH * 60 + (int) $startM;
        $endMinutes   = (int) $endH * 60 + (int) $endM;

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }
}