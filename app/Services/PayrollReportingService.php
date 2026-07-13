<?php

namespace App\Services;

use App\Models\Payroll;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayrollReportingService
{
    public function query(array $filters): Builder
    {
        return Payroll::query()
            ->with(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items'])
            ->when($filters['payroll_period_id'] ?? null, fn (Builder $query, $periodId) => $query->where('payroll_period_id', $periodId))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($search) {
                    $employeeQuery->where('employee_number', 'ilike', '%'.$search.'%')
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'ilike', '%'.$search.'%'));
                });
            })
            ->latest('id');
    }

    public function summary(array $filters): array
    {
        $records = $this->query($filters)->get([
            'id',
            'payroll_period_id',
            'employee_id',
            'status',
            'currency',
            'total_earnings',
            'total_deductions',
            'net_salary',
        ]);
        $active = $records->where('status', '!=', Payroll::STATUS_CANCELLED);

        return [
            'records_count' => $records->count(),
            'active_records_count' => $active->count(),
            'currency' => $records->pluck('currency')->filter()->first() ?? 'IDR',
            'total_earnings' => number_format((float) $active->sum('total_earnings'), 2, '.', ''),
            'total_deductions' => number_format((float) $active->sum('total_deductions'), 2, '.', ''),
            'total_net_salary' => number_format((float) $active->sum('net_salary'), 2, '.', ''),
            'status_counts' => collect([
                Payroll::STATUS_DRAFT,
                Payroll::STATUS_REVIEWED,
                Payroll::STATUS_FINALIZED,
                Payroll::STATUS_PAID,
                Payroll::STATUS_CANCELLED,
            ])->mapWithKeys(fn (string $status) => [$status => $records->where('status', $status)->count()])->all(),
        ];
    }

    public function csv(Collection $records): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'Period',
            'Employee Number',
            'Employee',
            'Department',
            'Position',
            'Status',
            'Currency',
            'Basic Salary',
            'Total Earnings',
            'Total Deductions',
            'Net Salary',
            'Attendance Days',
            'Absent Days',
            'Late Minutes',
            'Unpaid Leave Days',
            'Overtime Minutes',
        ]);

        foreach ($records as $payroll) {
            fputcsv($stream, [
                $payroll->period?->name,
                $payroll->employee?->employee_number,
                $payroll->employee?->user?->name,
                $payroll->employee?->departmentMaster?->name ?? $payroll->employee?->department,
                $payroll->employee?->positionMaster?->name ?? $payroll->employee?->position,
                $payroll->status,
                $payroll->currency,
                $payroll->basic_salary,
                $payroll->total_earnings,
                $payroll->total_deductions,
                $payroll->net_salary,
                $payroll->attendance_days,
                $payroll->absent_days,
                $payroll->late_minutes,
                $payroll->unpaid_leave_days,
                $payroll->overtime_minutes,
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $content;
    }

    public function reportLines(Collection $records, array $summary): array
    {
        $lines = [
            'Records: '.$summary['records_count'].' | Active: '.$summary['active_records_count'],
            'Total earnings: '.$summary['currency'].' '.$summary['total_earnings'],
            'Total deductions: '.$summary['currency'].' '.$summary['total_deductions'],
            'Total net salary: '.$summary['currency'].' '.$summary['total_net_salary'],
            str_repeat('-', 92),
            'Period | Employee | Status | Earnings | Deductions | Net Salary',
        ];

        foreach ($records as $payroll) {
            $lines[] = implode(' | ', [
                $payroll->period?->name ?? '-',
                ($payroll->employee?->employee_number ?? '-').' '.($payroll->employee?->user?->name ?? '-'),
                strtoupper($payroll->status),
                $payroll->total_earnings,
                $payroll->total_deductions,
                $payroll->net_salary,
            ]);
        }

        return $lines;
    }

    public function payslipLines(Payroll $payroll): array
    {
        $employee = $payroll->employee;
        $lines = [
            'Period: '.($payroll->period?->name ?? '-'),
            'Employee: '.($employee?->employee_number ?? '-').' - '.($employee?->user?->name ?? '-'),
            'Department: '.($employee?->departmentMaster?->name ?? $employee?->department ?? '-'),
            'Position: '.($employee?->positionMaster?->name ?? $employee?->position ?? '-'),
            'Status: '.strtoupper($payroll->status),
            str_repeat('-', 72),
            'EARNINGS',
        ];

        foreach ($payroll->items->where('type', 'earning') as $item) {
            $lines[] = $item->name.' ('.$item->code.'): '.$payroll->currency.' '.$item->amount;
        }

        $lines[] = 'Total earnings: '.$payroll->currency.' '.$payroll->total_earnings;
        $lines[] = str_repeat('-', 72);
        $lines[] = 'DEDUCTIONS';
        foreach ($payroll->items->where('type', 'deduction') as $item) {
            $lines[] = $item->name.' ('.$item->code.'): '.$payroll->currency.' '.$item->amount;
        }

        return [
            ...$lines,
            'Total deductions: '.$payroll->currency.' '.$payroll->total_deductions,
            str_repeat('=', 72),
            'NET SALARY: '.$payroll->currency.' '.$payroll->net_salary,
            str_repeat('-', 72),
            'Attendance days: '.$payroll->attendance_days,
            'Absent days: '.$payroll->absent_days,
            'Late minutes: '.$payroll->late_minutes,
            'Unpaid leave days: '.$payroll->unpaid_leave_days,
            'Overtime minutes: '.$payroll->overtime_minutes,
        ];
    }
}
