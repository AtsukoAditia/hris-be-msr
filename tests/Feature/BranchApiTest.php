<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BranchSeeder::class);
    }

    public function test_unauthenticated_user_cannot_list_branches(): void
    {
        $this->getJson('/api/v1/branches')->assertUnauthorized();
    }

    public function test_manager_can_list_search_filter_and_view_branches(): void
    {
        $this->actingAsRole('manager');
        $headOffice = Branch::where('code', 'HQ-JKT')->firstOrFail();

        $this->getJson('/api/v1/branches?search=Head%20Office&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'HQ-JKT')
            ->assertJsonPath('data.0.timezone', 'Asia/Jakarta');

        $this->getJson("/api/v1/branches/{$headOffice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $headOffice->id)
            ->assertJsonPath('data.name', 'Head Office Jakarta');
    }

    public function test_employee_cannot_read_branches(): void
    {
        $this->actingAsRole('employee');

        $this->getJson('/api/v1/branches')->assertForbidden();
    }

    public function test_manager_cannot_create_branch(): void
    {
        $this->actingAsRole('manager');

        $this->postJson('/api/v1/branches', $this->validPayload())->assertForbidden();
    }

    public function test_admin_can_create_branch_with_normalized_values(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/branches', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'BKS')
            ->assertJsonPath('data.name', 'Bekasi Branch')
            ->assertJsonPath('data.radius_meters', 200)
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.employees_count', 0);

        $this->assertDatabaseHas('branches', [
            'code' => 'BKS',
            'name' => 'Bekasi Branch',
            'address' => 'Bekasi',
            'radius_meters' => 200,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    public function test_branch_rejects_invalid_location_configuration(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/branches', [
            ...$this->validPayload(),
            'latitude' => 91,
            'longitude' => 181,
            'radius_meters' => 0,
            'timezone' => 'Invalid/Timezone',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude', 'radius_meters', 'timezone']);
    }

    public function test_branch_coordinates_must_be_provided_as_a_pair(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/branches', [
            ...$this->validPayload(),
            'longitude' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_branch_code_must_be_unique(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/branches', [
            ...$this->validPayload(),
            'code' => 'HQ-JKT',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_hr_can_update_branch(): void
    {
        $this->actingAsRole('hr');
        $branch = Branch::where('code', 'BDG')->firstOrFail();

        $this->putJson("/api/v1/branches/{$branch->id}", [
            'code' => ' bdg-01 ',
            'name' => ' Bandung Office ',
            'address' => ' Bandung City ',
            'latitude' => -6.9000000,
            'longitude' => 107.6000000,
            'radius_meters' => 250,
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.code', 'BDG-01')
            ->assertJsonPath('data.name', 'Bandung Office')
            ->assertJsonPath('data.address', 'Bandung City')
            ->assertJsonPath('data.radius_meters', 250)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_soft_delete_unused_branch(): void
    {
        $this->actingAsRole('admin');
        $branch = Branch::where('code', 'SBY')->firstOrFail();

        $this->deleteJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    public function test_branch_used_by_employee_cannot_be_deleted(): void
    {
        $this->actingAsRole('admin');
        $branch = Branch::where('code', 'HQ-JKT')->firstOrFail();
        $user = User::create([
            'name' => 'Assigned Branch Employee',
            'email' => 'assigned.branch@hris.test',
            'password' => 'password123',
            'role' => 'employee',
            'is_active' => true,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-BR-001',
            'nik' => '3171000000010001',
            'department' => 'IT',
            'position' => 'Software Engineer',
            'branch_id' => $branch->id,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/branches/{$branch->id}")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'deleted_at' => null,
        ]);
    }

    public function test_branch_seeder_is_idempotent_and_restores_deleted_data(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(BranchSeeder::class);

        $this->assertDatabaseCount('branches', 3);

        $branch = Branch::where('code', 'BDG')->firstOrFail();
        $branch->delete();
        $this->seed(BranchSeeder::class);

        $this->assertDatabaseCount('branches', 3);
        $this->assertDatabaseHas('branches', [
            'code' => 'BDG',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'code' => ' bks ',
            'name' => ' Bekasi Branch ',
            'address' => ' Bekasi ',
            'latitude' => -6.2382699,
            'longitude' => 106.9755726,
            'radius_meters' => 200,
            'timezone' => 'Asia/Jakarta',
        ];
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' Branch User',
            'email' => $role.'.branch@hris.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
