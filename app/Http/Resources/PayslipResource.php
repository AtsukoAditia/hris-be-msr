<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'period' => $this->whenLoaded('period', fn () => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'start_date' => $this->period->start_date?->toDateString(),
                'end_date' => $this->period->end_date?->toDateString(),
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'name' => $this->employee->user?->name,
                'department_name' => $this->employee->departmentMaster?->name ?? $this->employee->department,
                'position_name' => $this->employee->positionMaster?->name ?? $this->employee->position,
                'bank_name' => $this->employee->bank_name,
                'bank_account_masked' => $this->maskBankAccount($this->employee->bank_account),
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'source' => $item->source,
                'quantity' => $item->quantity,
                'rate' => $item->rate,
                'amount' => $item->amount,
            ])->values()),
            'generated_at' => $this->generated_at,
            'finalized_at' => $this->finalized_at,
            'paid_at' => $this->paid_at,
        ];
    }

    private function maskBankAccount(?string $account): ?string
    {
        if (! $account) {
            return null;
        }

        $visible = substr($account, -4);

        return str_repeat('*', max(strlen($account) - 4, 0)).$visible;
    }
}
