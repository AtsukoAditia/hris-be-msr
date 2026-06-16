<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EmployeeQueryService
{
    public const RELATIONS = ['user', 'departmentMaster', 'positionMaster.department', 'branch'];

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

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($filter) use ($search) {
                $filter->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('nik', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhere('employment_type', 'like', '%'.$search.'%')
                    ->orWhereHas('departmentMaster', fn ($master) => $master->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('positionMaster', fn ($master) => $master->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('branch', fn ($branch) => $branch->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $employees = $query->latest()->paginate($perPage);
        $employees->getCollection()->transform(fn (Employee $employee) => $this->transform($employee));

        return $employees;
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
        $employee->formatted_employee_number = $employee->employee_number
            ?: sprintf('%s-%04d', strtoupper(substr($departmentCode ?? $employee->department ?? 'EMP', 0, 3)), $employee->user_id ?? 0);
        $employee->face_image_url = $employee->face_image ? asset('storage/'.$employee->face_image) : null;
        $employee->is_face_registered = ! empty($employee->face_image);

        return $employee;
    }
}
