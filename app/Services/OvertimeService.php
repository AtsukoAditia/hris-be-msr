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
        $query = OvertimeRequest::with(['employee.user', 'overtimePolicy', 'approver'])
            ->latest('overtime_date');

        if ($actor->role === 'employee') {
            $query->where('employee_id', $actor->employee?->id ?? 0);
        } elseif ($actor->role === 'manager') {
            $managerId = $actor->employee?->id;
            $employeeIds = Employee::where('manager_id', $managerId)->pluck('id');
            if ($managerId) {
                $employeeIds->push($managerId);
            }
            $query->whereIn('employee_id', $employeeIds->unique());
        }

        foreach (['status', 'employee_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
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

            $minutes = $this->calculateMinutes($data['planned_start_time'], $data['planned_end_time']);
            $policy = OvertimePolicy::where('is_active', true)->findOrFail($data['overtime_policy_id']);
            if ($minutes > $policy->daily_max_minutes) {
                throw new \DomainException('Planned overtime exceeds the daily policy limit.');
            }

            return OvertimeRequest::create([
                'employee_id' => $employee->id,
                'overtime_policy_id' => $policy->id,
                'overtime_date' => $data['overtime_date'],
                'planned_start_time' => $data['planned_start_time'],
                'planned_end_time' => $data['planned_end_time'],
                'planned_minutes' => $minutes,
                'rate_multiplier' => $policy->rate_multiplier,
                'status' => OvertimeRequest::STATUS_PENDING,
                'reason' => $data['reason'],
                'attachment' => $data['attachment'] ?? null,
            ]);
        });
    }

    public function cancel(OvertimeRequest $request): OvertimeRequest
    {
        return $this->changeStatus($request, OvertimeRequest::STATUS_CANCELLED);
    }

    public function approve(OvertimeRequest $request, User $actor): OvertimeRequest
    {
        return $this->changeStatus($request, OvertimeRequest::STATUS_APPROVED, $actor);
    }

    public function reject(OvertimeRequest $request, User $actor, string $reason): OvertimeRequest
    {
        return $this->changeStatus($request, OvertimeRequest::STATUS_REJECTED, $actor, $reason);
    }

    public function recordActualMinutes(OvertimeRequest $request, int $minutes): OvertimeRequest
    {
        if ($request->status !== OvertimeRequest::STATUS_APPROVED) {
            throw new \DomainException('Actual minutes can only be recorded for approved requests.');
        }
        $request->update(['actual_minutes' => $minutes]);

        return $request->fresh();
    }

    private function changeStatus(OvertimeRequest $request, string $status, ?User $actor = null, ?string $reason = null): OvertimeRequest
    {
        return DB::transaction(function () use ($request, $status, $actor, $reason) {
            $request = OvertimeRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                throw new \DomainException('Only pending overtime requests can be processed.');
            }
            $request->update([
                'status' => $status,
                'approved_by' => $actor?->id,
                'approved_at' => $actor ? now() : null,
                'rejection_reason' => $reason,
            ]);

            return $request->fresh(['employee.user', 'overtimePolicy', 'approver']);
        });
    }

    private function calculateMinutes(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));
        $startValue = $startHour * 60 + $startMinute;
        $endValue = $endHour * 60 + $endMinute;

        return ($endValue <= $startValue ? $endValue + 1440 : $endValue) - $startValue;
    }
}
