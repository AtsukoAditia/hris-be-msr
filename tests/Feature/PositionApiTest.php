<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmployeeDepartmentSeeder;
use Database\Seeders\PositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PositionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DepartmentSeeder::class);
        $this->seed(EmployeeDepartmentSeeder::class);
    }

    public function test_unauthenticated_user_cannot_list_positions(): void
    {
        $this->getJson('/api/v1/positions')->assertUnauthorized();
    }

    public function test_manager_can_list_search_filter_and_view_positions(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $hr = Department::where('code', 'HR')->firstOrFail();

        $softwareEngineer = Position::create([
            'department_id' => $it->id,
            'code' => 'SOFTWARE-ENGINEER',
            'name' => 'Software Engineer',
            'description' => 'Build company systems',
            'is_active' => true,
        ]);

        Position::create([
            'department_id' => $hr->id,
            'code' => 'HR-STAFF',
            'name' => 'HR Staff',
            'is_active' => true,
        ]);

        $this->actingAsRole('manager');

        $this->getJson("/api/v1/positions?search=engineer&department_id={$it->id}&status=active")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SOFTWARE-ENGINEER')
            ->assertJsonPath('data.0.department.code', 'IT');

        $this->getJson("/api/v1/positions/{$softwareEngineer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $softwareEngineer->id)
            ->assertJsonPath('data.department.id', $it->id);
    }

    public function test_employee_cannot_read_positions(): void
    {
        $this->actingAsRole('employee');

        $this->getJson('/api/v1/positions')->assertForbidden();
    }

    public function test_manager_cannot_create_position(): void
    {
        $this->actingAsRole('manager');
        $it = Department::where('code', 'IT')->firstOrFail();

        $this->postJson('/api/v1/positions', [
            'department_id' => $it->id,
            'code' => 'QA',
            'name' => 'Quality Assurance',
        ])->assertForbidden();
    }

    public function test_admin_can_create_position_with_normalized_values(): void
    {
        $this->actingAsRole('admin');
        $it = Department::where('code', 'IT')->firstOrFail();

        $this->postJson('/api/v1/positions', [
            'department_id' => $it->id,
            'code' => ' qa ',
            'name' => ' Quality Assurance ',
            'description' => ' Test software quality ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'QA')
            ->assertJsonPath('data.name', 'Quality Assurance')
            ->assertJsonPath('data.department.id', $it->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('positions', [
            'department_id' => $it->id,
            'code' => 'QA',
            'name' => 'Quality Assurance',
            'description' => 'Test software quality',
            'is_active' => true,
        ]);
    }

    public function test_position_code_must_be_unique(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        Position::create([
            'department_id' => $it->id,
            'code' => 'QA',
            'name' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/positions', [
            'department_id' => $it->id,
            'code' => 'QA',
            'name' => 'Another QA',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_position_requires_an_active_department(): void
    {
        $finance = Department::where('code', 'FIN')->firstOrFail();
        $finance->update(['is_active' => false]);
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/positions', [
            'department_id' => $finance->id,
            'code' => 'FIN-LEAD',
            'name' => 'Finance Lead',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_hr_can_update_position_and_department(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $operations = Department::where('code', 'OPS')->firstOrFail();
        $position = Position::create([
            'department_id' => $it->id,
            'code' => 'SUPPORT',
            'name' => 'Support Staff',
            'is_active' => true,
        ]);
        $this->actingAsRole('hr');

        $this->putJson("/api/v1/positions/{$position->id}", [
            'department_id' => $operations->id,
            'code' => ' ops-support ',
            'name' => ' Operations Support ',
            'description' => '',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.department_id', $operations->id)
            ->assertJsonPath('data.code', 'OPS-SUPPORT')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_soft_delete_position(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $position = Position::create([
            'department_id' => $it->id,
            'code' => 'QA',
            'name' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $this->actingAsRole('admin');

        $this->deleteJson("/api/v1/positions/{$position->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
    }

    public function test_position_seeder_is_idempotent_and_restores_deleted_data(): void
    {
        $this->seed(PositionSeeder::class);
        $this->seed(PositionSeeder::class);

        $this->assertDatabaseCount('positions', 7);

        $position = Position::where('code', 'HR-STAFF')->firstOrFail();
        $position->delete();
        $this->seed(PositionSeeder::class);

        $this->assertDatabaseCount('positions', 7);
        $this->assertDatabaseHas('positions', [
            'code' => 'HR-STAFF',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'.position@hris.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
