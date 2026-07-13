<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeQueryService
{
    public const RELATIONS = ['user', 'departmentMaster', 'positionMaster.department', 'branch', 'manager.user', 'manager.positionMaster'];

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Employee::with(self::RELATIONS);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        } elseif ($request->filled('department')) {
            $department = trim((string) $request->department);
            $query->where(function ($filter) use ($department) {
                $filter->where('department', $department)
                    ->orWhereHas('departmentMaster', fn ($master) => $master->where('code', $department)->orWhere('name', $department));
            });
        }

        if ($request->filled('position_id')) {
            $query->where('position_id', $request->integer('position_id'));
        } elseif ($request->filled('position')) {
            $position = trim((string) $request->position);
            $query->where(function ($filter) use ($position) {
                $filter->where('position', $position)
                    ->orWhereHas('positionMaster', fn ($master) => $master->where('code', $position)->orWhere('name', $position));
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('manager_id')) {
            $managerId = strtolower(trim((string) $request->manager_id));

            if (in_array($managerId, ['none', 'null', 'unassigned'], true)) {
                $query->whereNull('manager_id');
            } else {
                $query->where('manager_id', (int) $managerId);
            }
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($filter) use ($search) {
                $filter->where('employee_number', 'ilike', '%'.$search.'%')
                    ->orWhere('nik', 'ilike', '%'.$search.'%')
                    ->orWhere('department', 'ilike', '%'.$search.'%')
                    ->orWhere('position', 'ilike', '%'.$search.'%')
                    ->orWhere('employment_type', 'ilike', '%'.$search.'%')
                    ->orWhereHas('departmentMaster', fn ($master) => $master->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%'))
                    ->orWhereHas('positionMaster', fn ($master) => $master->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%'))
                    ->orWhereHas('branch', fn ($branch) => $branch->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%')->orWhere('address', 'ilike', '%'.$search.'%'))
                    ->orWhereHas('manager', function ($manager) use ($search) {
                        $manager->where('employee_number', 'ilike', '%'.$search.'%')
                            ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'));
                    })
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'));
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $employees = $query->latest()->paginate($perPage);
        $employees->getCollection()->transform(fn (Employee $employee) => $this->transform($employee));

        return $employees;
    }

    public function managerOptions(Request $request): Collection
    {
        $query = Employee::query()
            ->with(['user:id,name,email,role,is_active', 'departmentMaster:id,code,name', 'positionMaster:id,code,name', 'branch:id,code,name'])
            ->where('is_active', true)
            ->whereHas('user', fn ($user) => $user->where('is_active', true));

        if ($request->filled('exclude_employee_id')) {
            $query->where('id', '!=', $request->integer('exclude_employee_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($filter) use ($search) {
                $filter->where('employee_number', 'ilike', '%'.$search.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'))
                    ->orWhereHas('positionMaster', fn ($position) => $position->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%'));
            });
        }

        return $query->limit(100)
            ->get()
            ->sortBy(fn (Employee $employee) => strtolower($employee->user?->name ?? $employee->employee_number))
            ->values()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'email' => $employee->user?->email,
                'role' => $employee->user?->role,
                'department_id' => $employee->department_id,
                'department_code' => $employee->departmentMaster?->code,
                'department_name' => $employee->departmentMaster?->name ?? $employee->department,
                'position_id' => $employee->position_id,
                'position_code' => $employee->positionMaster?->code,
                'position_name' => $employee->positionMaster?->name ?? $employee->position,
                'branch_id' => $employee->branch_id,
                'branch_code' => $employee->branch?->code,
                'branch_name' => $employee->branch?->name,
                'label' => sprintf(
                    '%s — %s (%s)',
                    $employee->user?->name ?? 'Unnamed Employee',
                    $employee->positionMaster?->name ?? $employee->position ?? 'No Position',
                    $employee->employee_number,
                ),
            ]);
    }

    public function load(Employee $employee): Employee
    {
        return $employee->load(self::RELATIONS);
    }

    public function transform(Employee $employee): Employee
    {
        $departmentCode = $employee->departmentMaster?->code;
        $positionCode = $employee->positionMaster?->code;

        $employee->department_code = $departmentCode;
        $employee->department_name = $employee->departmentMaster?->name ?? $employee->department;
        $employee->position_code = $positionCode;
        $employee->position_name = $employee->positionMaster?->name ?? $employee->position;
        $employee->branch_code = $employee->branch?->code;
        $employee->branch_name = $employee->branch?->name;
        $employee->manager_name = $employee->manager?->user?->name;
        $employee->manager_employee_number = $employee->manager?->employee_number;
        $employee->manager_position_name = $employee->manager?->positionMaster?->name ?? $employee->manager?->position;
        $employee->formatted_employee_number = $employee->employee_number
            ?: sprintf('%s-%04d', strtoupper(substr($departmentCode ?? $employee->department ?? 'EMP', 0, 3)), $employee->user_id ?? 0);
        $employee->face_image_url = $employee->face_image ? asset('storage/'.$employee->face_image) : null;
        $employee->is_face_registered = ! empty($employee->face_image);

        return $employee;
    }
}
