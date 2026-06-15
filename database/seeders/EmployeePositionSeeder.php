<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Services\EmployeePositionResolver;
use Illuminate\Database\Seeder;

class EmployeePositionSeeder extends Seeder
{
    public function run(): void
    {
        $resolver = app(EmployeePositionResolver::class);

        Employee::query()
            ->with('departmentMaster')
            ->whereNull('position_id')
            ->whereNotNull('position')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->each(function (Employee $employee) use ($resolver): void {
                if (! $employee->departmentMaster) {
                    return;
                }

                $position = $resolver->resolve(
                    ['position' => $employee->position],
                    $employee->departmentMaster,
                );

                $employee->update([
                    'position_id' => $position->id,
                ]);
            });
    }
}
