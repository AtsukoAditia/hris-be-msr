<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'year' => $this->year,
            'total_days' => (int) $this->opening_days,
            'used_days' => (int) $this->used_days,
            'pending_days' => (int) $this->remaining_days,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
