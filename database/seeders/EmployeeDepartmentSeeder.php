<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Services\EmployeeDepartmentResolver;
use Illuminate\Database\Seeder;

class EmployeeDepartmentSeeder extends Seeder
{
    public function run(): void
    {
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
}
