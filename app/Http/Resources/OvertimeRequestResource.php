<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', new EmployeeBasicResource($this->employee)),
            'overtime_policy_id' => $this->overtime_policy_id,
            'overtime_policy' => $this->whenLoaded('overtimePolicy', new OvertimePolicyResource($this->overtimePolicy)),
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'planned_minutes' => $this->planned_minutes,
            'actual_start_time' => $this->actual_start_time,
            'actual_end_time' => $this->actual_end_time,
            'actual_minutes' => $this->actual_minutes,
            'rate_multiplier' => $this->rate_multiplier,
            'status' => $this->status,
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
