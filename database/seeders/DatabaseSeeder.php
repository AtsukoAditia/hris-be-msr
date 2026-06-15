<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            ShiftSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            EmployeeDepartmentSeeder::class,
            PositionSeeder::class,
            EmployeePositionSeeder::class,
        ]);
    }
}
