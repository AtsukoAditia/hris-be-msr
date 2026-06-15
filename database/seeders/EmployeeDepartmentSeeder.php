<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Services\EmployeeDepartmentResolver;
use Illuminate\Database\Seeder;

class EmployeeDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureTransitionDepartments();

        $resolver = app(EmployeeDepartmentResolver::class);

        Employee::query()
            ->whereNull('department_id')
            ->whereNotNull('department')
            ->orderBy('id')
            ->each(function (Employee $employee) use ($resolver): void {
                $department = $resolver->resolve([
                    'department' => $employee->department,
                ]);

                $employee->update([
                    'department_id' => $department->id,
                ]);
            });
    }

    private function ensureTransitionDepartments(): void
    {
        $departments = [
            [
                'code' => 'MGT',
                'name' => 'Management',
                'description' => 'Mengelola arah strategis, tata kelola, dan pengambilan keputusan perusahaan.',
            ],
            [
                'code' => 'MKT',
                'name' => 'Marketing',
                'description' => 'Mengelola pemasaran, komunikasi, promosi, dan pertumbuhan pasar.',
            ],
        ];

        foreach ($departments as $data) {
            $department = Department::withTrashed()->firstOrNew([
                'code' => $data['code'],
            ]);

            if ($department->exists && $department->trashed()) {
                $department->restore();
            }

            $department->fill([
                ...$data,
                'is_active' => true,
            ])->save();
        }
    }
}
