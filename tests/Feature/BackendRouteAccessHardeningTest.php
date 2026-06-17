<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackendRouteAccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_shift_schedule_routes_are_not_shadowed_by_resource_show_route(): void
    {
        $admin = $this->user('Route Admin', 'route.admin@hris.test', 'admin');
        $employee = $this->employee($this->user('Route Employee', 'route.employee@hris.test'));
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/shift-schedules/employee/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id);

        $this->getJson('/api/v1/shift-schedules/date/2026-06-17')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_employee_cannot_view_another_employees_leave_detail(): void
    {
        $owner = $this->employee($this->user('Leave Owner', 'leave.owner@hris.test'));
        $otherUser = $this->user('Other Employee', 'other.employee@hris.test');
        $this->employee($otherUser);
        $leave = $this->leave($owner);
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/leaves/{$leave->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_employee_can_view_own_leave_and_privileged_role_can_view_any_leave(): void
    {
        $ownerUser = $this->user('Leave Owner', 'own.leave@hris.test');
        $owner = $this->employee($ownerUser);
        $leave = $this->leave($owner);

        Sanctum::actingAs($ownerUser);
        $this->getJson("/api/v1/leaves/{$leave->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $leave->id);

        $hr = $this->user('HR Reviewer', 'hr.reviewer@hris.test', 'hr');
        Sanctum::actingAs($hr);
        $this->getJson("/api/v1/leaves/{$leave->id}")
            ->assertOk()
            ->assertJsonPath('data.employee_id', $owner->id);
    }

    private function user(string $name, string $email, string $role = 'employee'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function employee(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => sprintf('EMP-%04d', $user->id),
            'nik' => sprintf('NIK-%04d', $user->id),
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
    }

    private function leave(Employee $employee): Leave
    {
        return Leave::create([
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'total_days' => 1,
            'reason' => 'Personal leave',
            'status' => Leave::STATUS_PENDING,
        ]);
    }
}
