<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin HRIS',
                'email' => 'admin@hris.test',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'HR Staff',
                'email' => 'hr@hris.test',
                'password' => Hash::make('password123'),
                'role' => 'hr',
                'is_active' => true,
            ],
            [
                'name' => 'Manager Operasional',
                'email' => 'manager@hris.test',
                'password' => Hash::make('password123'),
                'role' => 'manager',
                'is_active' => true,
            ],
            [
                'name' => 'Employee Demo',
                'email' => 'employee@hris.test',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
