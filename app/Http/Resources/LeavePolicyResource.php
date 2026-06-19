<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeavePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'year' => $this->year,
            'policy_name' => $this->policy_name,
            'default_quota' => $this->default_quota,
            'min_service_months' => $this->min_service_months,
            'accrual_type' => $this->accrual_type,
            'accrual_amount' => $this->accrual_amount,
            'max_carry_forward_days' => $this->max_carry_forward_days,
            'carry_forward_expiry_month' => $this->carry_forward_expiry_month,
            'carry_forward_expiry_months' => $this->carry_forward_expiry_months,
            'carry_forward_expiry_day' => $this->carry_forward_expiry_day,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
