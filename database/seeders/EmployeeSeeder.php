<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $defaultShiftId = Shift::where('code', 'OH')->value('id')
            ?? Shift::query()->value('id');

        $employees = [
            [
                'email' => 'admin@hris.test',
                'employee_number' => 'ADM-0001',
                'nik' => '3171000000000001',
                'phone' => '081200000001',
                'address' => 'Jakarta',
                'birth_date' => '1990-01-01',
                'gender' => 'male',
                'department' => 'Management',
                'position' => 'System Administrator',
                'join_date' => '2024-01-01',
                'employment_type' => 'permanent',
                'basic_salary' => 10000000,
                'bank_name' => 'BCA',
                'bank_account' => '0000000001',
                'is_active' => true,
            ],
            [
                'email' => 'hr@hris.test',
                'employee_number' => 'HRD-0002',
                'nik' => '3171000000000002',
                'phone' => '081200000002',
                'address' => 'Jakarta',
                'birth_date' => '1992-02-02',
                'gender' => 'female',
                'department' => 'Human Resource',
                'position' => 'HR Staff',
                'join_date' => '2024-02-01',
                'employment_type' => 'permanent',
                'basic_salary' => 7500000,
                'bank_name' => 'BCA',
                'bank_account' => '0000000002',
                'is_active' => true,
            ],
            [
                'email' => 'manager@hris.test',
                'employee_number' => 'OPS-0003',
                'nik' => '3171000000000003',
                'phone' => '081200000003',
                'address' => 'Jakarta',
                'birth_date' => '1988-03-03',
                'gender' => 'male',
                'department' => 'Operation',
                'position' => 'Operational Manager',
                'join_date' => '2024-03-01',
                'employment_type' => 'permanent',
                'basic_salary' => 9000000,
                'bank_name' => 'BCA',
                'bank_account' => '0000000003',
                'is_active' => true,
            ],
            [
                'email' => 'employee@hris.test',
                'employee_number' => 'EMP-0004',
                'nik' => '3171000000000004',
                'phone' => '081200000004',
                'address' => 'Jakarta',
                'birth_date' => '1998-04-04',
                'gender' => 'male',
                'department' => 'Operation',
                'position' => 'Staff Operation',
                'join_date' => '2024-04-01',
                'employment_type' => 'contract',
                'basic_salary' => 5500000,
                'bank_name' => 'BCA',
                'bank_account' => '0000000004',
                'is_active' => true,
            ],
        ];

        foreach ($employees as $employeeData) {
            $user = User::where('email', $employeeData['email'])->first();

            if (! $user) {
                continue;
            }

            unset($employeeData['email']);

            $employee = Employee::updateOrCreate(
                ['user_id' => $user->id],
                $employeeData
            );

            if ($defaultShiftId) {
                ShiftSchedule::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'schedule_date' => now()->toDateString(),
                    ],
                    [
                        'shift_id' => $defaultShiftId,
                        'created_by' => $user->id,
                        'notes' => 'Default demo shift schedule',
                    ]
                );
            }
        }
    }
}
