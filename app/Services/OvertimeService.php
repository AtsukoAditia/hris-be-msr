<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OvertimePolicy;
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
            ->latest('overtime_date');

        if ($actor->role === 'employee') {
            $query->where('employee_id', $actor->employee?->id ?? 0);
        } elseif ($actor->role === 'manager') {
            $managerEmployeeId = $actor->employee?->id;
            $employeeIds = Employee::where('manager_id', $managerEmployeeId)->pluck('id');

            if ($managerEmployeeId) {
                $employeeIds->push($managerEmployeeId);
            }

            $query->whereIn('employee_id', $employeeIds->unique());
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('overtime_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('overtime_date', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function submit(array $data, User $actor): OvertimeRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $employee = $actor->employee;

            if (! $employee) {
                throw new \DomainException('Employee profile is required to submit overtime.');
            }

            $plannedMinutes = $this->calculateMinutes(
                $data['planned_start_time'],
                $data['planned_end_time']
            );

            $policy = OvertimePolicy::query()
                ->where('is_active', true)
                ->findOrFail($data['overtime_policy_id']);

            if ($plannedMinutes > $policy->daily_max_minutes) {
                throw new \DomainException('Planned overtime exceeds the daily policy limit.');
            }

            return OvertimeRequest::create([
                'employee_id' => $employee->id,
                'overtime_policy_id' => $policy->id,
                'overtime_date' => $data['overtime_date'],
                'planned_start_time' => $data['planned_start_time'],
                'planned_end_time' => $data['planned_end_time'],
                'planned_minutes' => $plannedMinutes,
                'rate_multiplier' => $policy->rate_multiplier,
                'status' => OvertimeRequest::STATUS_PENDING,
                'reason' => $data['reason)],
                'attachment' => $data['attachment'] ?? null,
            ]);
        });
    }

    public function cancel(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest) {
            $overtimeRequest = OvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);

            if (! $overtimeRequest->isPending()) {
                throw new \DomainException('Only pending overtime requests can be cancelled.');
            }

            $overtimeRequest->update(['status' => OvertimeRequest::STATUS_CANCELLED]);

            return $overtimeRequest->fresh();
        });
    }

    public function approve(OvertimeRequest $overtimeRequest, User $actor): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $actor) {
            $overtimeRequest = OvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);

            if (! $overtimeRequest->isPending()) {
                throw new \DomainException('Only pending overtime requests can be approved.');
            }

            $overtimeRequest->update([
                'status' => OvertimeRequest::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            return $overtimeRequest->fresh(['employee.user', 'overtimePolicy', 'approver']);
        });
    }

    public function reject(OvertimeRequest $overtimeRequest, User $actor, string $rejectionReason): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $actor, $rejectionReason) {
            $overtimeRequest = OvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);

            if (! $overtimeRequest->isPending()) {
                throw new \DomainException('Only pending overtime requests can be rejected.');
            }

            $overtimeRequest->update([
                'status' => OvertimeRequest::STATUS_REJECTED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            return $overtimeRequest->fresh(['employee.user', 'overtimePolicy', 'approver']);
        });
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
        $endMinutes = (int) $endH * 60 + (int) $endM;

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }
}
