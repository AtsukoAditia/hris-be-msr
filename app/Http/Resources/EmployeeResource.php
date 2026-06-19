<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            'email' => $this->user?->email,
            'department_id' => $this->department_id,
            'department' => $this->departmentMaster?->name,
            'position_id' => $this->position_id,
            'position' => $this->positionMaster?->name,
            'branch_id' => $this->branch_id,
            'is_active' => $this->is_active,
        ];
    }
}
