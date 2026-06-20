<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'name' => $this->employee->user?->name,
                'email' => $this->employee->user?->email,
                'department_name' => $this->employee->departmentMaster?->name ?? $this->employee->department,
                'position_name' => $this->employee->positionMaster?->name ?? $this->employee->position,
            ]),
            'basic_salary' => $this->basic_salary,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn ($profileComponent) => [
                'id' => $profileComponent->id,
                'salary_component_id' => $profileComponent->salary_component_id,
                'amount' => $profileComponent->amount,
                'percentage' => $profileComponent->percentage,
                'formula' => $profileComponent->formula,
                'salary_component' => $profileComponent->salaryComponent ? (new SalaryComponentResource($profileComponent->salaryComponent))->resolve($request) : null,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
