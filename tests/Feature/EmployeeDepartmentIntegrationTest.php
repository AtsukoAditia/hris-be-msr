<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmployeeDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDepartmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DepartmentSeeder::class);
        $this->actingAsAdmin();
    }

    public function test_admin_can_create_employee_with_department_id(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();

        $response = $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $department->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.department', 'IT')
            ->assertJsonPath('data.department_code', 'IT')
            ->assertJsonPath('data.department_name', 'Information Technology')
            ->assertJsonPath('data.department_master.id', $department->id)
            ->assertJsonPath('data.department_master.code', 'IT');

        $this->assertDatabaseHas('employees', [
            'department_id' => $department->id,
            'department' => 'IT',
        ]);
    }

    public function test_legacy_department_alias_is_resolved_for_old_frontend_payload(): void
    {
        $operations = Department::where('code', 'OPS')->firstOrFail();

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload([
                'email' => 'legacy@hris.test',
                'nik' => '3171000000000102',
            ]),
            'department' => 'Operations',
        ])
            ->assertCreated()
            ->assertJsonPath('data.department_id', $operations->id)
            ->assertJsonPath('data.department', 'Operations')
            ->assertJsonPath('data.department_code', 'OPS')
            ->assertJsonPath('data.department_master.name', 'Operations');
    }

    public function test_inactive_department_cannot_be_assigned_to_employee(): void
    {
        $department = Department::where('code', 'MKT')->firstOrFail();
        $department->update(['is_active' => false]);

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $department->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_unknown_legacy_department_is_rejected(): void
    {
        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department' => 'Unknown Department',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department']);
    }

    public function test_employee_index_can_filter_by_department_id(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $hr = Department::where('code', 'HR')->firstOrFail();

        $this->createEmployee('it.employee@hris.test', '3171000000000201', $it);
        $this->createEmployee('hr.employee@hris.test', '3171000000000202', $hr);

        $this->getJson("/api/v1/employees?department_id={$it->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.department_id', $it->id)
            ->assertJsonPath('data.data.0.department_master.code', 'IT');
    }

    public function test_employee_department_can_be_updated_by_department_id(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $finance = Department::where('code', 'FIN')->firstOrFail();
        $employee = $this->createEmployee('update.employee@hris.test', '3171000000000301', $it);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            ...$this->validEmployeePayload([
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'nik' => $employee->nik,
            ]),
            'department_id' => $finance->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.department_id', $finance->id)
            ->assertJsonPath('data.department', 'FIN')
            ->assertJsonPath('data.department_master.code', 'FIN');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $finance->id,
            'department' => 'FIN',
        ]);
    }

    public function test_relation_seeder_backfills_legacy_employee_department(): void
    {
        $user = User::create([
            'name' => 'Legacy HR Employee',
            'email' => 'legacy.hr@hris.test',
            'password' => 'password123',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'HR-0999',
            'nik' => '3171000000000999',
            'department' => 'Human Resource',
            'position' => 'HR Staff',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);

        $this->seed(EmployeeDepartmentSeeder::class);

        $employee->refresh()->load('departmentMaster');

        $this->assertSame('HR', $employee->departmentMaster?->code);
        $this->assertTrue($employee->departmentMaster?->employees->contains($employee));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.integration@hris.test',
            'password' => 'password123',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    private function validEmployeePayload(array $overrides = []): array
    {
        return [
            'name' => 'Department Test Employee',
            'email' => 'department.employee@hris.test',
            'role' => 'employee',
            'nik' => '3171000000000101',
            'phone' => '081234567890',
            'position' => 'Software Engineer',
            'join_date' => '2026-06-15',
            'employment_type' => 'permanent',
            'status' => 'active',
            ...$overrides,
        ];
    }

    private function createEmployee(string $email, string $nik, Department $department): Employee
    {
        $user = User::create([
            'name' => 'Filter Employee',
            'email' => $email,
            'password' => 'password123',
            'role' => 'employee',
            'is_active' => true,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => $department->code.'-'.$user->id,
            'nik' => $nik,
            'department' => $department->code,
            'department_id' => $department->id,
            'position' => 'Staff',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
    }
}
