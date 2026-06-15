<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeMutationService
{
    public function __construct(
        private readonly EmployeeDepartmentResolver $departmentResolver,
        private readonly EmployeePositionResolver $positionResolver,
    ) {}

    public function create(Request $request): Employee
    {
        $validated = $this->validate($request);
        $department = $this->departmentResolver->resolve($validated);
        $position = $this->positionResolver->resolve($validated, $department);

        return DB::transaction(function () use ($validated, $department, $position) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make('password123'),
                'role' => $validated['role'],
                'is_active' => $this->resolveIsActive($validated),
            ]);

            return Employee::create([
                'user_id' => $user->id,
                'employee_number' => sprintf('%s-%04d', strtoupper(substr($department->code, 0, 3)), $user->id),
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'department' => $this->departmentResolver->legacyValue($department, $validated['department'] ?? null),
                'department_id' => $department->id,
                'position' => $this->positionResolver->legacyValue($position, $validated),
                'position_id' => $position->id,
                'join_date' => $validated['join_date'],
                'employment_type' => $validated['employment_type'] ?? 'permanent',
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });
    }

    public function update(Request $request, Employee $employee): Employee
    {
        $validated = $this->validate($request, $employee);
        $department = $this->departmentResolver->resolve($validated);
        $position = $this->positionResolver->resolve($validated, $department);

        DB::transaction(function () use ($validated, $employee, $department, $position) {
            $employee->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $this->resolveIsActive($validated),
            ]);

            $employee->update([
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'department' => $this->departmentResolver->legacyValue($department, $validated['department'] ?? null),
                'department_id' => $department->id,
                'position' => $this->positionResolver->legacyValue($position, $validated),
                'position_id' => $position->id,
                'join_date' => $validated['join_date'],
                'employment_type' => $validated['employment_type'] ?? 'permanent',
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });

        return $employee->refresh();
    }

    private function validate(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'role' => ['required', 'string', Rule::in(['admin', 'hr', 'manager', 'employee'])],
            'nik' => ['required', 'string', 'max:50', Rule::unique('employees', 'nik')->ignore($employee?->id)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'department_id' => [
                'nullable',
                'integer',
                'required_without:department',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'department' => ['nullable', 'required_without:department_id', 'string', 'max:100'],
            'position_id' => [
                'nullable',
                'integer',
                'required_without:position',
                Rule::exists('positions', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'position' => ['nullable', 'required_without:position_id', 'string', 'max:100'],
            'join_date' => ['required', 'date'],
            'employment_type' => ['nullable', 'string', Rule::in(['permanent', 'contract', 'internship'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function resolveIsActive(array $validated): bool
    {
        if (array_key_exists('is_active', $validated)) {
            return (bool) $validated['is_active'];
        }

        return ($validated['status'] ?? 'active') === 'active';
    }
}
