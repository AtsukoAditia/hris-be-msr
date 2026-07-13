<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSalaryProfile;
use App\Models\Leave;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\PayrollMoney;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function generatePeriod(PayrollPeriod $period, array $employeeIds, User $actor): Collection
    {
        return DB::transaction(function () use ($period, $employeeIds, $actor) {
            $lockedPeriod = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($lockedPeriod->status !== PayrollPeriod::STATUS_OPEN) {
                throw new DomainException('Payroll period is closed and cannot be generated.');
            }

            $employees = Employee::query()
                ->with('user')
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->when($employeeIds !== [], fn ($query) => $query->whereIn('id', $employeeIds))
                ->orderBy('id')
                ->get();

            if ($employees->isEmpty()) {
                throw new DomainException('No active employees were found for payroll generation.');
            }

            return $employees
                ->map(fn (Employee $employee) => $this->calculateAndPersist($lockedPeriod, $employee, $actor))
                ->values();
        });
    }

    public function recalculate(Payroll $payroll, User $actor): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor) {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);

            if ($lockedPayroll->status !== Payroll::STATUS_DRAFT) {
                throw new DomainException('Only draft payroll can be recalculated.');
            }

            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($lockedPayroll->payroll_period_id);
            $employee = Employee::query()->findOrFail($lockedPayroll->employee_id);

            return $this->calculateAndPersist($period, $employee, $actor, $lockedPayroll);
        });
    }

    public function submit(Payroll $payroll, User $actor): Payroll
    {
        return $this->transition($payroll, Payroll::STATUS_DRAFT, Payroll::STATUS_SUBMITTED, $actor, [
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ], ActivityAction::MANUAL_UPDATE);
    }

    public function review(Payroll $payroll, User $actor): Payroll
    {
        return $this->transition($payroll, Payroll::STATUS_SUBMITTED, Payroll::STATUS_REVIEWED, $actor, [
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ], ActivityAction::APPROVE);
    }

    public function approve(Payroll $payroll, User $actor): Payroll
    {
        return $this->transition($payroll, Payroll::STATUS_REVIEWED, Payroll::STATUS_APPROVED, $actor, [
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ], ActivityAction::APPROVE);
    }

    public function finalize(Payroll $payroll, User $actor): Payroll
    {
        return $this->transition($payroll, Payroll::STATUS_APPROVED, Payroll::STATUS_FINALIZED, $actor, [
            'finalized_by' => $actor->id,
            'finalized_at' => now(),
        ], ActivityAction::FINALIZE);
    }

    public function markPaid(Payroll $payroll, User $actor): Payroll
    {
        return $this->transition($payroll, Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID, $actor, [
            'paid_by' => $actor->id,
            'paid_at' => now(),
        ], ActivityAction::UPDATE);
    }

    public function simulate(int $employeeId, int $periodId, User $actor): array
    {
        $period = PayrollPeriod::findOrFail($periodId);
        $employee = Employee::findOrFail($employeeId);
        $basicSalaryCents = PayrollMoney::toCents($employee->basic_salary ?? 0);
        $summary = $this->buildInputSummary($period, $employee, $basicSalaryCents);

        $profile = EmployeeSalaryProfile::query()
            ->with('components.salaryComponent')
            ->where('employee_id', $employeeId)
            ->effectiveFor($period->start_date->toDateString(), $period->end_date->toDateString())
            ->latest('effective_from')
            ->first();

        $items = [['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => 'earning', 'amount' => PayrollMoney::fromCents($basicSalaryCents)]];

        foreach ($profile?->components ?? [] as $profileComponent) {
            $component = $profileComponent->salaryComponent;
            if (!$component || !$component->is_active) continue;
            $amountCents = match ($component->calculation_type) {
                SalaryComponent::CALCULATION_PERCENTAGE => PayrollMoney::percentage($basicSalaryCents, $profileComponent->percentage ?? $component->percentage),
                default => PayrollMoney::toCents($profileComponent->amount ?? $component->default_amount),
            };
            $items[] = ['code' => $component->code, 'name' => $component->name, 'type' => $component->type, 'amount' => PayrollMoney::fromCents($amountCents)];
        }

        if ($summary['overtime_amount_cents'] > 0) $items[] = ['code' => 'OVERTIME', 'name' => 'Approved Overtime', 'type' => 'earning', 'amount' => PayrollMoney::fromCents($summary['overtime_amount_cents'])];
        if ($summary['absent_deduction_cents'] > 0) $items[] = ['code' => 'ABSENCE', 'name' => 'Absence Deduction', 'type' => 'deduction', 'amount' => PayrollMoney::fromCents($summary['absent_deduction_cents'])];
        if ($summary['unpaid_leave_deduction_cents'] > 0) $items[] = ['code' => 'UNPAID_LEAVE', 'name' => 'Unpaid Leave', 'type' => 'deduction', 'amount' => PayrollMoney::fromCents($summary['unpaid_leave_deduction_cents'])];

        $totalEarnings = collect($items)->where('type', 'earning')->sum(fn ($i) => PayrollMoney::toCents($i['amount']));
        $totalDeductions = collect($items)->where('type', 'deduction')->sum(fn ($i) => PayrollMoney::toCents($i['amount']));

        return [
            'employee_id' => $employeeId,
            'employee_name' => $employee->user?->name ?? 'N/A',
            'period' => $period->name,
            'basic_salary' => PayrollMoney::fromCents($basicSalaryCents),
            'items' => $items,
            'attendance' => ['attendance_days' => $summary['attendance_days'], 'absent_days' => $summary['absent_days'], 'late_minutes' => $summary['late_minutes'], 'overtime_minutes' => $summary['overtime_minutes']],
            'total_earnings' => PayrollMoney::fromCents($totalEarnings),
            'total_deductions' => PayrollMoney::fromCents($totalDeductions),
            'net_salary' => PayrollMoney::fromCents($totalEarnings - $totalDeductions),
            'simulated' => true,
        ];
    }

    public function cancel(Payroll $payroll, User $actor, string $reason): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor, $reason) {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);

            if (in_array($lockedPayroll->status, [Payroll::STATUS_PAID, Payroll::STATUS_CANCELLED], true)) {
                throw new DomainException('Paid or cancelled payroll cannot be cancelled.');
            }

            $oldStatus = $lockedPayroll->status;
            $lockedPayroll->update([
                'status' => Payroll::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ]);

            ActivityLog::log(ActivityAction::CANCEL, Payroll::class, $lockedPayroll->id, [
                'old' => ['status' => $oldStatus],
                'new' => ['status' => Payroll::STATUS_CANCELLED, 'cancellation_reason' => trim($reason)],
            ]);

            return $lockedPayroll->fresh($this->relations());
        });
    }

    public function relations(): array
    {
        return [
            'period',
            'employee.user',
            'employee.departmentMaster',
            'employee.positionMaster',
            'salaryProfile.components.salaryComponent',
            'items.salaryComponent',
        ];
    }

    private function calculateAndPersist(PayrollPeriod $period, Employee $employee, User $actor, ?Payroll $lockedPayroll = null): Payroll
    {
        $payroll = $lockedPayroll ?? Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->lockForUpdate()
            ->first();

        if ($payroll && in_array($payroll->status, [Payroll::STATUS_REVIEWED, Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID, Payroll::STATUS_CANCELLED], true)) {
            throw new DomainException("Payroll for employee {$employee->employee_number} is no longer recalculable.");
        }

        $profile = EmployeeSalaryProfile::query()
            ->with('components.salaryComponent')
            ->where('employee_id', $employee->id)
            ->effectiveFor($period->start_date->toDateString(), $period->end_date->toDateString())
            ->latest('effective_from')
            ->first();

        $basicSalaryCents = PayrollMoney::toCents($profile?->basic_salary ?? $employee->basic_salary ?? 0);
        $currency = $profile?->currency ?? 'IDR';
        $summary = $this->buildInputSummary($period, $employee, $basicSalaryCents);

        $items = [[
            'salary_component_id' => null,
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => SalaryComponent::TYPE_EARNING,
            'source' => 'basic_salary',
            'quantity' => '1.0000',
            'rate' => PayrollMoney::fromCents($basicSalaryCents),
            'amount_cents' => $basicSalaryCents,
            'metadata' => ['salary_profile_id' => $profile?->id],
        ]];

        foreach ($profile?->components ?? [] as $profileComponent) {
            $component = $profileComponent->salaryComponent;

            if (! $component || ! $component->is_active) {
                continue;
            }

            $amountCents = match ($component->calculation_type) {
                SalaryComponent::CALCULATION_PERCENTAGE => PayrollMoney::percentage($basicSalaryCents, $profileComponent->percentage ?? $component->percentage),
                default => PayrollMoney::toCents($profileComponent->amount ?? $component->default_amount),
            };

            $items[] = [
                'salary_component_id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'source' => 'salary_component',
                'quantity' => '1.0000',
                'rate' => $component->calculation_type === SalaryComponent::CALCULATION_PERCENTAGE
                    ? (string) ($profileComponent->percentage ?? $component->percentage ?? 0)
                    : PayrollMoney::fromCents($amountCents),
                'amount_cents' => $amountCents,
                'metadata' => [
                    'calculation_type' => $component->calculation_type,
                    'formula' => $profileComponent->formula ?? $component->formula,
                ],
            ];
        }

        if ($summary['overtime_amount_cents'] > 0) {
            $items[] = [
                'salary_component_id' => null,
                'code' => 'OVERTIME',
                'name' => 'Approved Overtime',
                'type' => SalaryComponent::TYPE_EARNING,
                'source' => 'approved_overtime',
                'quantity' => number_format($summary['overtime_minutes'] / 60, 4, '.', ''),
                'rate' => null,
                'amount_cents' => $summary['overtime_amount_cents'],
                'metadata' => ['minutes' => $summary['overtime_minutes']],
            ];
        }

        if ($summary['absent_deduction_cents'] > 0) {
            $items[] = [
                'salary_component_id' => null,
                'code' => 'ABSENCE',
                'name' => 'Absence Deduction',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'source' => 'attendance',
                'quantity' => number_format($summary['absent_days'], 4, '.', ''),
                'rate' => PayrollMoney::fromCents(PayrollMoney::ratio($basicSalaryCents, 1, 22)),
                'amount_cents' => $summary['absent_deduction_cents'],
                'metadata' => ['working_days_divisor' => 22],
            ];
        }

        if ($summary['unpaid_leave_deduction_cents'] > 0) {
            $items[] = [
                'salary_component_id' => null,
                'code' => 'UNPAID_LEAVE',
                'name' => 'Unpaid Leave Deduction',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'source' => 'approved_unpaid_leave',
                'quantity' => number_format($summary['unpaid_leave_days'], 4, '.', ''),
                'rate' => PayrollMoney::fromCents(PayrollMoney::ratio($basicSalaryCents, 1, 22)),
                'amount_cents' => $summary['unpaid_leave_deduction_cents'],
                'metadata' => ['working_days_divisor' => 22],
            ];
        }

        $totalEarningsCents = collect($items)->where('type', SalaryComponent::TYPE_EARNING)->sum('amount_cents');
        $totalDeductionsCents = collect($items)->where('type', SalaryComponent::TYPE_DEDUCTION)->sum('amount_cents');
        $netSalaryCents = $totalEarningsCents - $totalDeductionsCents;

        $attributes = [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'employee_salary_profile_id' => $profile?->id,
            'status' => Payroll::STATUS_DRAFT,
            'currency' => $currency,
            'basic_salary' => PayrollMoney::fromCents($basicSalaryCents),
            'total_earnings' => PayrollMoney::fromCents($totalEarningsCents),
            'total_deductions' => PayrollMoney::fromCents($totalDeductionsCents),
            'net_salary' => PayrollMoney::fromCents($netSalaryCents),
            'attendance_days' => $summary['attendance_days'],
            'absent_days' => $summary['absent_days'],
            'late_minutes' => $summary['late_minutes'],
            'unpaid_leave_days' => $summary['unpaid_leave_days'],
            'overtime_minutes' => $summary['overtime_minutes'],
            'input_snapshot' => [
                'period' => [
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'cutoff_start_date' => $period->cutoff_start_date->toDateString(),
                    'cutoff_end_date' => $period->cutoff_end_date->toDateString(),
                ],
                'basic_salary' => PayrollMoney::fromCents($basicSalaryCents),
                'attendance' => [
                    'attendance_days' => $summary['attendance_days'],
                    'absent_days' => $summary['absent_days'],
                    'late_minutes' => $summary['late_minutes'],
                ],
                'leave' => ['unpaid_leave_days' => $summary['unpaid_leave_days']],
                'overtime' => ['approved_actual_minutes' => $summary['overtime_minutes']],
            ],
            'generated_by' => $actor->id,
            'generated_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'finalized_by' => null,
            'finalized_at' => null,
            'paid_by' => null,
            'paid_at' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];

        if ($payroll) {
            $payroll->update($attributes);
        } else {
            $payroll = Payroll::create($attributes);
        }

        $payroll->items()->delete();
        $payroll->items()->createMany(array_map(function (array $item) {
            $amountCents = $item['amount_cents'];
            unset($item['amount_cents']);
            $item['amount'] = PayrollMoney::fromCents($amountCents);

            return $item;
        }, $items));

        ActivityLog::log(ActivityAction::MANUAL_UPDATE, Payroll::class, $payroll->id, [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => Payroll::STATUS_DRAFT,
            'net_salary' => PayrollMoney::fromCents($netSalaryCents),
        ]);

        return $payroll->fresh($this->relations());
    }

    private function buildInputSummary(PayrollPeriod $period, Employee $employee, int $basicSalaryCents): array
    {
        $attendanceQuery = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date]);

        $attendanceDays = (clone $attendanceQuery)->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentDays = (clone $attendanceQuery)->where('status', 'absent')->count();
        $lateMinutes = (int) (clone $attendanceQuery)->sum('late_minutes');

        $unpaidLeaveDays = Leave::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', Leave::STATUS_APPROVED)
            ->whereHas('leaveType', fn ($query) => $query->where('is_paid', false))
            ->whereDate('start_date', '<=', $period->end_date)
            ->whereDate('end_date', '>=', $period->start_date)
            ->get()
            ->sum(function (Leave $leave) use ($period) {
                $start = Carbon::parse($leave->start_date)->max(Carbon::parse($period->start_date));
                $end = Carbon::parse($leave->end_date)->min(Carbon::parse($period->end_date));

                return $start->diffInDays($end) + 1;
            });

        $overtimeRequests = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', OvertimeRequest::STATUS_APPROVED)
            ->whereBetween('overtime_date', [$period->start_date, $period->end_date])
            ->whereNotNull('actual_minutes')
            ->where('actual_minutes', '>', 0)
            ->get();

        $overtimeMinutes = (int) $overtimeRequests->sum('actual_minutes');
        $overtimeAmountCents = (int) $overtimeRequests->sum(fn (OvertimeRequest $request) => PayrollMoney::multipliedRatio(
            $basicSalaryCents,
            (int) $request->actual_minutes,
            $request->rate_multiplier,
            173 * 60,
        ));

        return [
            'attendance_days' => $attendanceDays,
            'absent_days' => $absentDays,
            'late_minutes' => $lateMinutes,
            'unpaid_leave_days' => (int) $unpaidLeaveDays,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_amount_cents' => $overtimeAmountCents,
            'absent_deduction_cents' => PayrollMoney::ratio($basicSalaryCents, $absentDays, 22),
            'unpaid_leave_deduction_cents' => PayrollMoney::ratio($basicSalaryCents, (int) $unpaidLeaveDays, 22),
        ];
    }

    private function transition(Payroll $payroll, string $expectedStatus, string $newStatus, User $actor, array $attributes, ActivityAction $action): Payroll
    {
        return DB::transaction(function () use ($payroll, $expectedStatus, $newStatus, $actor, $attributes, $action) {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);

            if ($lockedPayroll->status !== $expectedStatus) {
                throw new DomainException("Payroll must be {$expectedStatus} before it can become {$newStatus}.");
            }

            $lockedPayroll->update(['status' => $newStatus, ...$attributes]);

            ActivityLog::log($action, Payroll::class, $lockedPayroll->id, [
                'old' => ['status' => $expectedStatus],
                'new' => ['status' => $newStatus],
                'actor_id' => $actor->id,
            ]);

            return $lockedPayroll->fresh($this->relations());
        });
    }
}
