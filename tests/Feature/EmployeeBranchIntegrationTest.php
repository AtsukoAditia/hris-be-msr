<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmployeeBranchSeeder;
use Database\Seeders\EmployeeDepartmentSeeder;
use Database\Seeders\PositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeBranchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DepartmentSeeder::class);
        $this->seed(EmployeeDepartmentSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->seed(BranchSeeder::class);
        $this->actingAsAdmin();
    }

    public function test_admin_can_create_employee_with_branch_id(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $branch = Branch::where('code', 'HQ-JKT')->firstOrFail();

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $department->id,
            'position_id' => $position->id,
            'branch_id' => $branch->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.branch.code', 'HQ-JKT')
            ->assertJsonPath('data.branch_code', 'HQ-JKT')
            ->assertJsonPath('data.branch_name', 'Head Office Jakarta');

        $this->assertDatabaseHas('employees', [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_inactive_branch_cannot_be_assigned(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $branch = Branch::where('code', 'BDG')->firstOrFail();
        $branch->update(['is_active' => false]);

        $this->postJson('/api/v1/employees', [
            ...$this->validEmployeePayload(),
            'department_id' => $department->id,
            'position_id' => $position->id,
            'branch_id' => $branch->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_employee_index_can_filter_by_branch_id(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $headOffice = Branch::where('code', 'HQ-JKT')->firstOrFail();
        $bandung = Branch::where('code', 'BDG')->firstOrFail();

        $this->createEmployee('hq.employee@hris.test', '3171000000020001', $department, $position, $headOffice);
        $this->createEmployee('bdg.employee@hris.test', '3171000000020002', $department, $position, $bandung);

        $this->getJson("/api/v1/employees?branch_id={$bandung->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.branch_id', $bandung->id)
            ->assertJsonPath('data.data.0.branch.code', 'BDG');
    }

    public function test_employee_search_includes_branch_name_and_code(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $bandung = Branch::where('code', 'BDG')->firstOrFail();

        $this->createEmployee('search.branch@hris.test', '3171000000021001', $department, $position, $bandung);

        $this->getJson('/api/v1/employees?search=Bandung')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.branch.code', 'BDG');
    }

    public function test_employee_branch_can_be_updated(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $headOffice = Branch::where('code', 'HQ-JKT')->firstOrFail();
        $surabaya = Branch::where('code', 'SBY')->firstOrFail();
        $employee = $this->createEmployee(
            'update.branch@hris.test',
            '3171000000022001',
            $department,
            $position,
            $headOffice,
        );

        $this->putJson("/api/v1/employees/{$employee->id}", [
            ...$this->validEmployeePayload([
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'nik' => $employee->nik,
            ]),
            'department_id' => $department->id,
            'position_id' => $position->id,
            'branch_id' => $surabaya->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.branch_id', $surabaya->id)
            ->assertJsonPath('data.branch_code', 'SBY');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'branch_id' => $surabaya->id,
        ]);
    }

    public function test_legacy_update_without_branch_id_preserves_existing_branch(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $branch = Branch::where('code', 'HQ-JKT')->firstOrFail();
        $employee = $this->createEmployee(
            'legacy.branch@hris.test',
            '3171000000023001',
            $department,
            $position,
            $branch,
        );

        $this->putJson("/api/v1/employees/{$employee->id}", [
            ...$this->validEmployeePayload([
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'nik' => $employee->nik,
            ]),
            'department_id' => $department->id,
            'position_id' => $position->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_employee_branch_seeder_backfills_default_branch(): void
    {
        $department = Department::where('code', 'IT')->firstOrFail();
        $position = Position::where('code', 'SOFTWARE-ENGINEER')->firstOrFail();
        $employee = $this->createEmployee(
            'backfill.branch@hris.test',
            '3171000000024001',
            $department,
            $position,
            null,
        );

        $this->seed(EmployeeBranchSeeder::class);

        $employee->refresh()->load('branch');

        $this->assertSame('HQ-JKT', $employee->branch?->code);
        $this->assertTrue($employee->branch?->employees->contains($employee));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Branch Integration',
            'email' => 'admin.branch.integration@hris.test',
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
            'name' => 'Branch Test Employee',
            'email' => 'branch.employee@hris.test',
            'role' => 'employee',
            'nik' => '3171000000020000',
            'phone' => '081234567890',
            'join_date' => '2026-06-17',
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
        ?Branch $branch,
    ): Employee {
        $user = User::create([
            'name' => 'Branch Filter Employee',
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
            'branch_id' => $branch?->id,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
    }
}
