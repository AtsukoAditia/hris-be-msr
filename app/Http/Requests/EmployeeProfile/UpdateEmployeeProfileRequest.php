<?php

namespace App\Http\Requests\EmployeeProfile;

use App\Models\Employee;
use App\Support\EmployeeProfileFieldPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(EmployeeProfileFieldPolicy::normalize($this->all()));
    }

    public function rules(): array
    {
        $profileId = $this->targetEmployee()?->profile()->value('id');

        return EmployeeProfileFieldPolicy::rules($profileId);
    }

    private function targetEmployee(): ?Employee
    {
        $routeEmployee = $this->route('employee');

        if ($routeEmployee instanceof Employee) {
            return $routeEmployee;
        }

        return $this->user()?->employee;
    }
}
