<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_departments(): void
    {
        $this->getJson('/api/v1/departments')
            ->assertUnauthorized();
    }

    public function test_manager_can_list_search_and_filter_departments(): void
    {
        Department::create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'Technology systems',
            'is_active' => true,
        ]);

        Department::create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'is_active' => true,
        ]);

        Department::create([
            'code' => 'OPS',
            'name' => 'Operations',
            'description' => 'Technology operations',
            'is_active' => false,
        ]);

        $this->actingAsRole('manager');

        $this->getJson('/api/v1/departments?search=technology&status=active')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'IT');
    }

    public function test_employee_cannot_read_departments(): void
    {
        $this->actingAsRole('employee');

        $this->getJson('/api/v1/departments')
            ->assertForbidden();
    }

    public function test_manager_cannot_create_department(): void
    {
        $this->actingAsRole('manager');

        $this->postJson('/api/v1/departments', [
            'code' => 'IT',
            'name' => 'Information Technology',
        ])->assertForbidden();
    }

    public function test_admin_can_create_department_with_normalized_values(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/departments', [
            'code' => ' it ',
            'name' => ' Information Technology ',
            'description' => ' Core company systems ',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'IT')
            ->assertJsonPath('data.name', 'Information Technology')
            ->assertJsonPath('data.description', 'Core company systems')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('departments', [
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'Core company systems',
            'is_active' => true,
        ]);
    }

    public function test_department_code_must_be_unique(): void
    {
        Department::create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);

        $this->actingAsRole('admin');

        $this->postJson('/api/v1/departments', [
            'code' => 'IT',
            'name' => 'Another Technology Department',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_hr_can_update_department(): void
    {
        $department = Department::create([
            'code' => 'FIN',
            'name' => 'Finance',
            'description' => 'Finance department',
            'is_active' => true,
        ]);

        $this->actingAsRole('hr');

        $this->putJson("/api/v1/departments/{$department->id}", [
            'code' => ' fin ',
            'name' => ' Finance and Accounting ',
            'description' => '',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.code', 'FIN')
            ->assertJsonPath('data.name', 'Finance and Accounting')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'code' => 'FIN',
            'name' => 'Finance and Accounting',
            'description' => null,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_soft_delete_department(): void
    {
        $department = Department::create([
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $this->actingAsRole('admin');

        $this->deleteJson("/api/v1/departments/{$department->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_department_seeder_is_idempotent_and_restores_deleted_master_data(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->assertDatabaseCount('departments', 4);

        $department = Department::where('code', 'IT')->firstOrFail();
        $department->delete();

        $this->seed(DepartmentSeeder::class);

        $this->assertDatabaseCount('departments', 4);
        $this->assertDatabaseHas('departments', [
            'code' => 'IT',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'@hris.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
