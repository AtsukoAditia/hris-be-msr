<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'employee_id' => $this->employee_id,
            'employee_salary_profile_id' => $this->employee_salary_profile_id,
            'status' => $this->status,
            'currency' => $this->currency,
            'basic_salary' => $this->basic_salary,
            'total_earnings' => $this->total_earnings,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            'attendance_days' => $this->attendance_days,
            'absent_days' => $this->absent_days,
            'late_minutes' => $this->late_minutes,
            'unpaid_leave_days' => $this->unpaid_leave_days,
            'overtime_minutes' => $this->overtime_minutes,
            'input_snapshot' => $this->input_snapshot,
            'period' => $this->whenLoaded('period', fn () => (new PayrollPeriodResource($this->period))->resolve($request)),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'name' => $this->employee->user?->name,
                'email' => $this->employee->user?->email,
                'department_name' => $this->employee->departmentMaster?->name ?? $this->employee->department,
                'position_name' => $this->employee->positionMaster?->name ?? $this->employee->position,
            ]),
            'salary_profile' => $this->whenLoaded('salaryProfile', fn () => $this->salaryProfile ? (new EmployeeSalaryProfileResource($this->salaryProfile))->resolve($request) : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'salary_component_id' => $item->salary_component_id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'source' => $item->source,
                'quantity' => $item->quantity,
                'rate' => $item->rate,
                'amount' => $item->amount,
                'metadata' => $item->metadata,
            ])->values()),
            'generated_at' => $this->generated_at,
            'reviewed_at' => $this->reviewed_at,
            'finalized_at' => $this->finalized_at,
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
