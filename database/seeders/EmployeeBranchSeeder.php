<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeBranchSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBranchId = Branch::where('code', 'HQ-JKT')->value('id');

        if (! $defaultBranchId) {
            return;
        }

        Employee::query()
            ->whereNull('branch_id')
            ->update(['branch_id' => $defaultBranchId]);
    }
}
