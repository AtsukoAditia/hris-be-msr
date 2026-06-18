<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Validation\Rule;

class EmployeeProfileFieldPolicy
{
    public const DIRECT_SELF_SERVICE_FIELDS = [
        'phone',
        'address',
        'personal_email',
        'alternate_phone',
        'domicile_address',
        'city',
        'province',
        'postal_code',
    ];

    public const APPROVAL_REQUIRED_FIELDS = [
        'birth_date',
        'gender',
        'place_of_birth',
        'marital_status',
        'blood_type',
        'religion',
        'nationality',
        'identity_address',
        'tax_number',
        'social_security_number',
        'health_insurance_number',
    ];

    public const EMPLOYEE_FIELDS = [
        'phone',
        'address',
        'birth_date',
        'gender',
    ];

    public const PROFILE_FIELDS = [
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
    ];

    public static function allFields(): array
    {
        return array_values(array_unique(array_merge(
            self::DIRECT_SELF_SERVICE_FIELDS,
            self::APPROVAL_REQUIRED_FIELDS,
        )));
    }

    public static function rules(?int $profileId = null): array
    {
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

    public static function normalize(array $input, ?array $fields = null): array
    {
        $normalized = [];

        foreach ($fields ?? self::allFields() as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            $normalized[$field] = $value === null || trim((string) $value) === ''
                ? null
                : trim((string) $value);
        }

        foreach (['personal_email', 'marital_status', 'gender'] as $field) {
            if (isset($normalized[$field])) {
                $normalized[$field] = strtolower($normalized[$field]);
            }
        }

        if (isset($normalized['blood_type'])) {
            $normalized['blood_type'] = strtoupper($normalized['blood_type']);
        }

        return $normalized;
    }

    public static function currentValues(Employee $employee, array $fields): array
    {
        $employee->loadMissing('profile');
        $values = [];

        foreach ($fields as $field) {
            if (in_array($field, self::EMPLOYEE_FIELDS, true)) {
                $value = $employee->{$field};
                $values[$field] = $field === 'birth_date'
                    ? $value?->format('Y-m-d')
                    : $value;

                continue;
            }

            $values[$field] = $employee->profile?->{$field};
        }

        return self::normalize($values, $fields);
    }
}
