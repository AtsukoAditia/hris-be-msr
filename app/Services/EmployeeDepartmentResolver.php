<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeDepartmentResolver
{
    public function resolve(array $validated): Department
    {
        if (! empty($validated['department_id'])) {
            $department = Department::query()
                ->active()
                ->find($validated['department_id']);

            if ($department) {
                return $department;
            }

            throw ValidationException::withMessages([
                'department_id' => ['Departemen yang dipilih tidak tersedia atau tidak aktif.'],
            ]);
        }

        $legacyDepartment = trim((string) ($validated['department'] ?? ''));
        $normalized = Str::lower(preg_replace('/\s+/', ' ', $legacyDepartment) ?? $legacyDepartment);

        $aliases = [
            'it' => 'IT',
            'information technology' => 'IT',
            'hr' => 'HR',
            'human resource' => 'HR',
            'human resources' => 'HR',
            'fin' => 'FIN',
            'finance' => 'FIN',
            'ops' => 'OPS',
            'operation' => 'OPS',
            'operations' => 'OPS',
            'mgt' => 'MGT',
            'management' => 'MGT',
            'mkt' => 'MKT',
            'marketing' => 'MKT',
        ];

        $department = Department::query()
            ->active()
            ->where(function ($query) use ($aliases, $legacyDepartment, $normalized) {
                if (isset($aliases[$normalized])) {
                    $query->where('code', $aliases[$normalized]);

                    return;
                }

                $query
                    ->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhere('name', $legacyDepartment);
            })
            ->first();

        if (! $department) {
            throw ValidationException::withMessages([
                'department' => ['Departemen harus menggunakan master data yang aktif.'],
            ]);
        }

        return $department;
    }

    public function legacyValue(Department $department, ?string $submittedValue = null): string
    {
        $submittedValue = trim((string) $submittedValue);

        return $submittedValue !== '' ? $submittedValue : $department->code;
    }
}
