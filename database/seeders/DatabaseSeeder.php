<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            BranchSeeder::class,
            ShiftSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            EmployeeDepartmentSeeder::class,
            PositionSeeder::class,
            EmployeePositionSeeder::class,
            EmployeeBranchSeeder::class,
            PayrollSeeder::class,
            ShiftSwapRequestSeeder::class,
            AttendanceSeeder::class,
            ScheduleConflictLogSeeder::class,
            NotificationSeeder::class,
            TrainingSeeder::class,
        ]);
    }
}
