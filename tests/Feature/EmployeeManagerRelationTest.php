<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeManagerRelationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Position $position;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([DepartmentSeeder::class, PositionSeeder::class, BranchSeeder::class]);
        $this->department = Department::where('code', 'IT')->firstOrFail();
        $this->position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $this->branch = Branch::where('code', 'HQ-JKT')->firstOrFail();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin.manager@hris.test',
            'password' => 'password123',
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
    }

    public function test_employee_can_be_created_with_manager_and_response_summary(): void
    {
        $manager = $this->employee('Manager', 'manager@hris.test', '301', role: 'manager');

        $this->postJson('/api/v1/employees', $this->newPayload(['manager_id' => $manager->id]))
            ->assertCreated()
            ->assertJsonPath('data.manager_id', $manager->id)
            ->assertJsonPath('data.manager_name', 'Manager')
            ->assertJsonPath('data.manager_employee_number', $manager->employee_number)
            ->assertJsonPath('data.manager_position_name', 'Software Engineer');

        $report = Employee::where('nik', '300')->firstOrFail();
        $this->assertTrue($report->manager()->is($manager));
        $this->assertTrue($manager->directReports()->whereKey($report->id)->exists());
    }

    public function test_self_and_circular_manager_relations_are_rejected(): void
    {
        $first = $this->employee('First', 'first@hris.test', '302', role: 'manager');
        $second = $this->employee('Second', 'second@hris.test', '303', manager: $first, role: 'manager');

        $this->putJson("/api/v1/employees/{$first->id}", $this->updatePayload($first, ['manager_id' => $first->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manager_id');

        $this->putJson("/api/v1/employees/{$first->id}", $this->updatePayload($first, ['manager_id' => $second->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manager_id');
    }

    public function test_manager_update_can_preserve_change_and_clear_relation(): void
    {
        $first = $this->employee('First Manager', 'first.manager@hris.test', '304', role: 'manager');
        $second = $this->employee('Second Manager', 'second.manager@hris.test', '305', role: 'manager');
        $report = $this->employee('Report', 'report@hris.test', '306', manager: $first);

        $this->putJson("/api/v1/employees/{$report->id}", $this->updatePayload($report))
            ->assertOk()
            ->assertJsonPath('data.manager_id', $first->id);

        $this->putJson("/api/v1/employees/{$report->id}", $this->updatePayload($report, ['manager_id' => $second->id]))
            ->assertOk()
            ->assertJsonPath('data.manager_name', 'Second Manager');

        $this->putJson("/api/v1/employees/{$report->id}", $this->updatePayload($report->refresh(), ['manager_id' => null]))
            ->assertOk()
            ->assertJsonPath('data.manager_id', null);
    }

    public function test_employee_list_filters_and_searches_by_manager(): void
    {
        $manager = $this->employee('Jakarta Lead', 'jakarta.lead@hris.test', '307', role: 'manager');
        $report = $this->employee('Jakarta Report', 'jakarta.report@hris.test', '308', manager: $manager);
        $this->employee('Other Employee', 'other@hris.test', '309');

        $this->getJson("/api/v1/employees?manager_id={$manager->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $report->id);

        $this->getJson('/api/v1/employees?search=Jakarta Lead')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $report->id,
                'manager_name' => 'Jakarta Lead',
            ]);
    }

    public function test_manager_options_only_return_active_candidates_and_support_exclusion(): void
    {
        $manager = $this->employee('Available Manager', 'available@hris.test', '310', role: 'manager');
        $current = $this->employee('Current Employee', 'current@hris.test', '311');
        $this->employee('Inactive Manager', 'inactive@hris.test', '312', active: false, role: 'manager');

        $this->getJson("/api/v1/employees/manager-options?exclude_employee_id={$current->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $manager->id)
            ->assertJsonPath('data.0.name', 'Available Manager');
    }

    public function test_deleting_manager_clears_manager_id_from_direct_reports(): void
    {
        $manager = $this->employee('Deleted Manager', 'deleted@hris.test', '313', role: 'manager');
        $report = $this->employee('Orphan Report', 'orphan@hris.test', '314', manager: $manager);

        $this->deleteJson("/api/v1/employees/{$manager->id}")->assertOk();

        $this->assertSoftDeleted('employees', ['id' => $manager->id]);
        $this->assertDatabaseHas('employees', ['id' => $report->id, 'manager_id' => null]);
    }

    private function newPayload(array $overrides = []): array
    {
        return [
            'name' => 'New Employee',
            'email' => 'new.employee@hris.test',
            'role' => 'employee',
            'nik' => '300',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'branch_id' => $this->branch->id,
            'join_date' => '2026-06-17',
            'employment_type' => 'permanent',
            'status' => 'active',
            ...$overrides,
        ];
    }

    private function updatePayload(Employee $employee, array $overrides = []): array
    {
        return [
            'name' => $employee->user->name,
            'email' => $employee->user->email,
            'role' => $employee->user->role,
            'nik' => $employee->nik,
            'department_id' => $employee->department_id,
            'position_id' => $employee->position_id,
            'branch_id' => $employee->branch_id,
            'join_date' => $employee->join_date->format('Y-m-d'),
            'employment_type' => $employee->employment_type,
            'status' => $employee->is_active ? 'active' : 'inactive',
            ...$overrides,
        ];
    }

    private function employee(
        string $name,
        string $email,
        string $nik,
        bool $active = true,
        ?Employee $manager = null,
        string $role = 'employee',
    ): Employee {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => $active,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => sprintf('IT-%04d', $user->id),
            'nik' => $nik,
            'department' => $this->department->code,
            'department_id' => $this->department->id,
            'position' => $this->position->code,
            'position_id' => $this->position->id,
            'branch_id' => $this->branch->id,
            'manager_id' => $manager?->id,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => $active,
        ]);
    }
}
