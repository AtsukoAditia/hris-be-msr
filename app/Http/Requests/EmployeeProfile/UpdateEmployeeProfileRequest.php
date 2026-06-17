<?php

namespace App\Http\Requests\EmployeeProfile;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ([
            'phone',
            'address',
            'personal_email',
            'alternate_phone',
            'place_of_birth',
            'marital_status',
            'blood_type',
            'religion',
            'nationality',
            'identity_address',
            'domicile_address',
            'city',
            'province',
            'postal_code',
            'tax_number',
            'social_security_number',
            'health_insurance_number',
        ] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $values[$field] = $value === null || trim((string) $value) === ''
                    ? null
                    : trim((string) $value);
            }
        }

        if (isset($values['personal_email'])) {
            $values['personal_email'] = strtolower($values['personal_email']);
        }

        if (isset($values['blood_type'])) {
            $values['blood_type'] = strtoupper($values['blood_type']);
        }

        if (isset($values['marital_status'])) {
            $values['marital_status'] = strtolower($values['marital_status']);
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        $employee = $this->targetEmployee();
        $profileId = $employee?->profile()->value('id');

        return [
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'personal_email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('employee_profiles', 'personal_email')->ignore($profileId),
            ],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'place_of_birth' => ['sometimes', 'nullable', 'string', 'max:100'],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'blood_type' => ['sometimes', 'nullable', Rule::in(['A', 'B', 'AB', 'O', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'religion' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
            'identity_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'domicile_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'tax_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employee_profiles', 'tax_number')->ignore($profileId),
            ],
            'social_security_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employee_profiles', 'social_security_number')->ignore($profileId),
            ],
            'health_insurance_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employee_profiles', 'health_insurance_number')->ignore($profileId),
            ],
        ];
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
