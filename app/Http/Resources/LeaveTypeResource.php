<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_paid' => $this->is_paid,
            'requires_attachment' => $this->requires_attachment,
            'requires_balance' => $this->requires_balance,
            'max_consecutive_days' => $this->max_consecutive_days,
            'min_service_months' => $this->min_service_months,
            'gender_restriction' => $this->gender_restriction,
            'max_days_per_year' => $this->max_days_per_year,
            'default_days_per_year' => $this->default_days_per_year,
            'carry_forward_enabled' => $this->carry_forward_enabled,
            'max_carry_forward_days' => $this->max_carry_forward_days,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'policies_count' => $this->whenCounted('policies'),
            'leaves_count' => $this->whenCounted('leaves'),
        ];
    }
}
