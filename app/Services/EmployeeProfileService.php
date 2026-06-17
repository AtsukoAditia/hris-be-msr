<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EmployeeProfileService
{
    private const EMPLOYEE_FIELDS = [
        'phone',
        'address',
        'birth_date',
        'gender',
    ];

    private const PROFILE_FIELDS = [
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

    private const COMPLETION_FIELDS = [
        'phone',
        'address',
        'birth_date',
        'gender',
        'personal_email',
        'place_of_birth',
        'marital_status',
        'identity_address',
        'domicile_address',
        'city',
        'province',
        'primary_emergency_contact',
    ];

    public function update(Employee $employee, array $validated): Employee
    {
        DB::transaction(function () use ($employee, $validated): void {
            $employeeData = Arr::only($validated, self::EMPLOYEE_FIELDS);

            if ($employeeData !== []) {
                $employee->update($employeeData);
            }

            $profileData = Arr::only($validated, self::PROFILE_FIELDS);

            if ($profileData !== []) {
                $employee->profile()->updateOrCreate([], $profileData);
            }
        });

        return $employee->refresh();
    }

    public function transform(Employee $employee): array
    {
        $employee->load([
            'user:id,name,email,role,is_active',
            'departmentMaster:id,code,name',
            'positionMaster:id,department_id,code,name',
            'branch:id,code,name,address,timezone',
            'manager.user:id,name,email',
            'manager.positionMaster:id,code,name',
            'profile',
            'emergencyContacts',
        ]);

        $profile = $employee->profile;
        $contacts = $employee->emergencyContacts;

        $profileData = collect(self::PROFILE_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $profile?->{$field}])
            ->all();

        $completionValues = [
            'phone' => $employee->phone,
            'address' => $employee->address,
            'birth_date' => $employee->birth_date,
            'gender' => $employee->gender,
            'personal_email' => $profile?->personal_email,
            'place_of_birth' => $profile?->place_of_birth,
            'marital_status' => $profile?->marital_status,
            'identity_address' => $profile?->identity_address,
            'domicile_address' => $profile?->domicile_address,
            'city' => $profile?->city,
            'province' => $profile?->province,
            'primary_emergency_contact' => $contacts->firstWhere('is_primary', true)?->id,
        ];

        $completedFields = collect(self::COMPLETION_FIELDS)
            ->filter(fn (string $field) => filled($completionValues[$field] ?? null))
            ->values();
        $totalFields = count(self::COMPLETION_FIELDS);

        return [
            'employee' => [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'work_email' => $employee->user?->email,
                'role' => $employee->user?->role,
                'phone' => $employee->phone,
                'address' => $employee->address,
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'gender' => $employee->gender,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'employment_type' => $employee->employment_type,
                'is_active' => $employee->is_active,
                'department' => $employee->departmentMaster ? [
                    'id' => $employee->departmentMaster->id,
                    'code' => $employee->departmentMaster->code,
                    'name' => $employee->departmentMaster->name,
                ] : null,
                'position' => $employee->positionMaster ? [
                    'id' => $employee->positionMaster->id,
                    'code' => $employee->positionMaster->code,
                    'name' => $employee->positionMaster->name,
                ] : null,
                'branch' => $employee->branch ? [
                    'id' => $employee->branch->id,
                    'code' => $employee->branch->code,
                    'name' => $employee->branch->name,
                    'address' => $employee->branch->address,
                    'timezone' => $employee->branch->timezone,
                ] : null,
                'manager' => $employee->manager ? [
                    'id' => $employee->manager->id,
                    'employee_number' => $employee->manager->employee_number,
                    'name' => $employee->manager->user?->name,
                    'position_name' => $employee->manager->positionMaster?->name ?? $employee->manager->position,
                ] : null,
            ],
            'profile' => array_merge([
                'id' => $profile?->id,
                'employee_id' => $employee->id,
            ], $profileData),
            'emergency_contacts' => $contacts
                ->map(fn ($contact) => $this->transformContact($contact))
                ->values()
                ->all(),
            'completion' => [
                'percentage' => (int) round(($completedFields->count() / $totalFields) * 100),
                'completed_fields' => $completedFields->all(),
                'total_fields' => $totalFields,
                'missing_fields' => collect(self::COMPLETION_FIELDS)->diff($completedFields)->values()->all(),
            ],
        ];
    }

    public function transformContact($contact): array
    {
        return [
            'id' => $contact->id,
            'employee_id' => $contact->employee_id,
            'name' => $contact->name,
            'relationship' => $contact->relationship,
            'phone' => $contact->phone,
            'alternate_phone' => $contact->alternate_phone,
            'email' => $contact->email,
            'address' => $contact->address,
            'is_primary' => $contact->is_primary,
            'notes' => $contact->notes,
            'created_at' => $contact->created_at?->toISOString(),
            'updated_at' => $contact->updated_at?->toISOString(),
        ];
    }
}
