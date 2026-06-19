<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create()->id,
            'user_name' => $this->faker->name,
            'user_email' => $this->faker->safeEmail,
            'user_role' => $this->faker->randomElement(['admin', 'hr', 'manager', 'employee']),
            'module' => $this->faker->randomElement(['employee', 'attendance', 'leave', 'profile', 'document', 'shift', 'report']),
            'action' => $this->faker->randomElement(['create', 'update', 'delete', 'view', 'approve', 'reject', 'manual_correction']),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'endpoint' => $this->faker->randomElement(['/api/v1/employees', '/api/v1/attendance', '/api/v1/leaves', '/api/v1/profile', '/api/v1/documents']),
            'route_name' => $this->faker->randomElement(['employees.show', 'attendance.index', 'leave.show', 'profile.show', 'document.show']),
            'response_status' => 200,
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent(),
            'request_payload' => $this->faker->randomFloat(3, 1, 1000),
            'response_payload' => null,
            'description' => $this->faker->sentence(5),
            'logged_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'latitude' => $this->faker->randomFloat(6, -90, 90),
            'longitude' => $this->faker->randomFloat(6, -180, 180),
        ];
    }
}
