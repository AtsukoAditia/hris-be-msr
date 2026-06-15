<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmployeeDepartmentSeeder;
use Database\Seeders\EmployeePositionSeeder;
use Database\Seeders\PositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeePositionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DepartmentSeeder::class);
        $this->seed(EmployeeDepartmentSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->actingAsAdmin();
    }

    public function test_admin_can_create_employee_with_position_id(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $department->id,
            'position_id' => $position->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.position_id', $position->id)
            ->assertJsonPath('data.position', 'SOFTWARE-ENGINEER')
            ->assertJsonPath('data.position_code', 'SOFTWARE-ENGINEER')
            ->assertJsonPath('data.position_name', 'Software Engineer')
            ->assertJsonPath('data.position_master.department.code', 'IT');

        $this->assertDatabaseHas('employees', [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'position' => 'SOFTWARE-ENGINEER',
        ]);
    }

    public function test_position_must_belong_to_selected_department(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $hrPosition = Position::where('code', 'HR-STAFF')->firstOrFail();

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $it->id,
            'position_id' => $hrPosition->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_inactive_position_cannot_be_assigned(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $position->update(['is_active' => false]);

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $it->id,
            'position_id' => $position->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_legacy_position_alias_is_resolved_during_transition(): void
    {
        $hr = Department::where('code', 'HR')->firstOrFail();
        $hrPosition = Position::where('code', 'HR-STAFF')->firstOrFail();

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload([
                'email' => 'legacy.position@hris.test',
                'nik' => '3171000000001102',
            ]),
            'department_id' => $hr->id,
            'position' => 'HR Staff',
        ])
            ->assertCreated()
            ->assertJsonPath('data.position_id', $hrPosition->id)
            ->assertJsonPath('data.position', 'HR Staff')
            ->assertJsonPath('data.position_code', 'HR-STAFF');
    }

    public function test_employee_index_can_filter_by_position_id(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $hr = Department::where('code', 'HR')->firstOrFail();
        $softwareEngineer = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $hrStaff = Position::where('code', 'HR-STAFF')->firstOrFail();

        $this->createEmployee('it.position@hris.test', '3171000000001201', $it, $softwareEngineer);
        $this->createEmployee('hr.position@hris.test', '3171000000001202', $hr, $hrStaff);

        $this->getJson("/api/v1/employees?position_id={$softwareEngineer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.position_id', $softwareEngineer->id)
            ->assertJsonPath('data.data.0.position_master.code', 'SOFTWARE-ENGINEER');
    }

    public function test_employee_department_and_position_can_be_updated_together(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $operations = Department::where('code', 'OPS')->firstOrFail();
        $softwareEngineer = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $operationsStaff = Position::where('code', 'OPS-STAFF')->firstOrFail();
        $employee = $this->createEmployee(
            'update.position@hris.test',
            '3171000000001301',
            $it,
            $softwareEngineer,
        );

        $this->putJson("/api/v1/employees/{$employee->id}", [
            ...$this->validEmployeePayload([
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'nik' => $employee->nik,
            ]),
            'department_id' => $operations->id,
            'position_id' => $operationsStaff->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.department_id', $operations->id)
            ->assertJsonPath('data.position_id', $operationsStaff->id)
            ->assertJsonPath('data.position_code', 'OPS-STAFF');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $operations->id,
            'position_id' => $operationsStaff->id,
        ]);
    }

    public function test_relation_seeder_backfills_legacy_employee_position(): void
    {
        $operations = Department::where('code', 'OPS')->firstOrFail();
        $user = User::create([
            'name' => 'Legacy Operations Employee',
            'email' => 'legacy.operations@hris.test',
            'password' => 'password123',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'OPS-0999',
            'nik' => '3171000000001999',
            'department' => 'Operations',
            'department_id' => $operations->id,
            'position' => 'Staff Operation',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);

        $this->seed(EmployeePositionSeeder::class);

        $employee->refresh()->load('positionMaster');

        $this->assertSame('OPS-STAFF', $employee->positionMaster?->code);
        $this->assertTrue($employee->positionMaster?->employees->contains($employee));
        $this->assertTrue($operations->positions->contains($employee->positionMaster));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Position Integration',
            'email' => 'admin.position.integration@hris.test',
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
            'name' => 'Position Test Employee',
            'email' => 'position.employee@hris.test',
            'role' => 'employee',
            'nik' => '3171000000001101',
            'phone' => '081234567890',
            'join_date' => '2026-06-15',
            'employment_type' => 'permanent',
            'status' => 'active',
            ...$overrides,
        ];
    }

    private function createEmployee(
        string $email,
        string $nik,
        Department $department,
        Position $position,
    ): Employee {
        $user = User::create([
            'name' => 'Position Filter Employee',
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
            'position' => $position->code,
            'position_id' => $position->id,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
    }
}
