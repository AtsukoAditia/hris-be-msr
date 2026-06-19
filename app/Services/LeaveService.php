<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    /**
     * List leave requests with filters and authorization scope.
     */
    public function list(?Employee $employee, array $filters = []): LengthAwarePaginator
    {
        $query = Leave::query()
            ->with(['employee.user', 'approver', 'leaveType']);

        if ($employee) {
            $query->where('employee_id', $employee->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['employee_ids']) && is_array($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('end_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Employee submits a leave request.
     */
    public function submit(Employee $employee, array $data): Leave
    {
        return DB::transaction(function () use ($employee, $data) {
            $leaveType = LeaveType::where('id', $data['leave_type_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            $year = $startDate->year;

            // Calculate working days excluding weekends and holidays
            $totalDays = $this->calculateWorkingDays($startDate, $endDate);

            // Prevent overlapping leave in pending/approved state
            $overlapping = Leave::where('employee_id', $employee->id)
                ->whereIn('status', [Leave::STATUS_PENDING, Leave::STATUS_APPROVED])
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($nested) use ($startDate, $endDate) {
                            $nested->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($overlapping) {
                throw new \DomainException('Sudah ada pengajuan cuti pada rentang tanggal tersebut.');
            }

            // Provision or fetch balance when leave type requires it.
            // Auto-provisioning keeps first-year onboarding simple and avoids a
            // hard block when HR has not yet seeded balances.
            if ($leaveType->requires_balance) {
                $balance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                $opening = (int) ($leaveType->max_days_per_year ?? 12);

                if (! $balance) {
                    $balance = LeaveBalance::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                        'opening_days' => $opening,
                        'used_days' => 0,
                        'remaining_days' => $opening,
                    ]);
                }

                if ($balance->remaining_days < $totalDays) {
                    throw new \DomainException('Sisa saldo cuti tidak mencukupi.');
                }

                $before = (int) $balance->remaining_days;
                $balance->used_days += $totalDays;
                $balance->remaining_days -= $totalDays;
                $balance->save();

                LeaveBalanceTransaction::create([
                    'leave_balance_id' => $balance->id,
                    'reference_type' => Leave::class,
                    'reference_id' => null, // Backfilled below
                    'transaction_type' => 'deduction',
                    'amount' => $totalDays,
                    'change' => -$totalDays,
                    'balance_before' => $before,
                    'balance_after' => (int) $balance->remaining_days,
                    'description' => 'Pengajuan cuti',
                ]);
            }

            // Create leave request
            $leave = Leave::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'leave_type' => $leaveType->code ?? $leaveType->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => $data['reason'],
                'attachment' => $data['attachment'] ?? null,
                'status' => Leave::STATUS_PENDING,
            ]);

            // Update transaction reference
            if (isset($balance)) {
                LeaveBalanceTransaction::where('leave_balance_id', $balance->id)
                    ->whereNull('reference_id')
                    ->where('transaction_type', 'deduction')
                    ->latest()
                    ->first()
                    ?->update(['reference_id' => $leave->id]);
            }

            ActivityLog::log(
                ActivityAction::CREATE,
                Leave::class,
                $leave->id,
                []
            );

            return $leave->refresh();
        });
    }

    /**
     * Approve a leave request.
     */
    public function approve(Leave $leave, ?string $note = null): Leave
    {
        return DB::transaction(function () use ($leave, $note) {
            $leave = Leave::where('id', $leave->id)
                ->lockForUpdate()
                ->first();

            if (! $leave->isPending()) {
                throw new \DomainException('Hanya pengajuan dengan status pending yang dapat disetujui.');
            }

            $leave->update([
                'status' => Leave::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            ActivityLog::log(
                ActivityAction::APPROVE,
                Leave::class,
                $leave->id,
                ['note' => $note]
            );

            return $leave->refresh();
        });
    }

    /**
     * Reject a leave request.
     */
    public function reject(Leave $leave, string $reason): Leave
    {
        return DB::transaction(function () use ($leave, $reason) {
            $leave = Leave::where('id', $leave->id)
                ->lockForUpdate()
                ->first();

            if (! $leave->isPending()) {
                throw new \DomainException('Hanya pengajuan dengan status pending yang dapat ditolak.');
            }

            // Restore balance if leave type requires it
            if ($leave->leaveType && $leave->leaveType->requires_balance) {
                $balance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $leave->start_date->year)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->used_days -= $leave->total_days;
                    $balance->remaining_days += $leave->total_days;
                    $balance->save();

                    LeaveBalanceTransaction::create([
                        'leave_balance_id' => $balance->id,
                        'reference_type' => Leave::class,
                        'reference_id' => $leave->id,
                        'transaction_type' => 'adjustment',
                        'amount' => $leave->total_days,
                        'change' => $leave->total_days,
                        'balance_before' => $balance->remaining_days - $leave->total_days,
                        'balance_after' => $balance->remaining_days,
                        'description' => 'Pengembalian saldo - cuti ditolak',
                    ]);
                }
            }

            $leave->update([
                'status' => Leave::STATUS_REJECTED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            ActivityLog::log(
                ActivityAction::REJECT,
                Leave::class,
                $leave->id,
                ['rejection_reason' => $reason]
            );

            return $leave->refresh();
        });
    }

    /**
     * Cancel a pending leave request (employee).
     */
    public function cancel(Leave $leave): Leave
    {
        return DB::transaction(function () use ($leave) {
            $leave = Leave::where('id', $leave->id)
                ->lockForUpdate()
                ->first();

            if (! $leave->isPending()) {
                throw new \DomainException('Hanya pengajuan dengan status pending yang dapat dibatalkan.');
            }

            // Restore balance if leave type requires it
            if ($leave->leaveType && $leave->leaveType->requires_balance) {
                $balance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $leave->start_date->year)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->used_days -= $leave->total_days;
                    $balance->remaining_days += $leave->total_days;
                    $balance->save();

                    LeaveBalanceTransaction::create([
                        'leave_balance_id' => $balance->id,
                        'reference_type' => Leave::class,
                        'reference_id' => $leave->id,
                        'transaction_type' => 'adjustment',
                        'amount' => $leave->total_days,
                        'change' => $leave->total_days,
                        'balance_before' => $balance->remaining_days - $leave->total_days,
                        'balance_after' => $balance->remaining_days,
                        'description' => 'Pengembalian saldo - cuti dibatalkan',
                    ]);
                }
            }

            $leave->update([
                'status' => Leave::STATUS_CANCELLED,
            ]);

            ActivityLog::log(
                ActivityAction::CANCEL,
                Leave::class,
                $leave->id,
                []
            );

            return $leave->refresh();
        });
    }

    /**
     * Calculate working days excluding weekends and holidays.
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        $days = 0;
        $current = $startDate->copy();

        while ($current->lessThanOrEqualTo($endDate)) {
            // Skip weekends
            if (! $current->isWeekend()) {
                // Skip holidays
                if (! in_array($current->format('Y-m-d'), $holidays)) {
                    $days++;
                }
            }

            $current->addDay();
        }

        return max(1, $days);
    }
}
