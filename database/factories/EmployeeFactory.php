<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_number' => 'EMP'.fake()->unique()->numerify('#####'),
            'nik' => fake()->unique()->numerify('##############'),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'join_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'employment_type' => fake()->randomElement(['permanent', 'contract', 'internship']),
            'basic_salary' => fake()->randomFloat(2, 3000000, 20000000),
            'is_active' => true,
        ];
    }
}
