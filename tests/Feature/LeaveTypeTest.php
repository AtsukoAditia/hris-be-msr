<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $hrUser;
    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->hrUser = User::factory()->create(['role' => 'hr']);
        $this->employeeUser = User::factory()->create(['role' => 'employee']);
    }

    public function test_admin_can_list_leave_types(): void
    {
        LeaveType::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/leave-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'is_paid',
                        'requires_attachment',
                        'requires_balance',
                        'max_consecutive_days',
                        'min_service_months',
                        'gender_restriction',
                        'max_days_per_year',
                        'default_days_per_year',
                        'carry_forward_enabled',
                        'max_carry_forward_days',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);
    }

    public function test_hr_can_list_leave_types(): void
    {
        LeaveType::factory()->count(2)->create();

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/admin/leave-types');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_list_leave_types(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->getJson('/api/v1/admin/leave-types');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_leave_type(): void
    {
        $data = [
            'code' => 'SICK',
            'name' => 'Sick Leave',
            'description' => 'Leave for illness',
            'is_paid' => true,
            'requires_attachment' => true,
            'requires_balance' => true,
            'max_consecutive_days' => 30,
            'min_service_months' => 0,
            'gender_restriction' => 'all',
            'max_days_per_year' => 30,
            'default_days_per_year' => 10,
            'carry_forward_enabled' => false,
            'max_carry_forward_days' => null,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Leave type created successfully.',
                'data' => [
                    'code' => 'SICK',
                    'name' => 'Sick Leave',
                ]
            ]);

        $this->assertDatabaseHas('leave_types', [
            'code' => 'SICK',
            'name' => 'Sick Leave',
        ]);
    }

    public function test_hr_can_create_leave_type(): void
    {
        $data = [
            'code' => 'MATERNITY',
            'name' => 'Maternity Leave',
            'description' => 'Leave for childbirth',
            'is_paid' => true,
            'requires_attachment' => true,
            'requires_balance' => true,
            'max_consecutive_days' => 90,
            'min_service_months' => 12,
            'gender_restriction' => 'female',
            'max_days_per_year' => 90,
            'default_days_per_year' => 90,
            'carry_forward_enabled' => false,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->hrUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(201);
    }

    public function test_employee_cannot_create_leave_type(): void
    {
        $data = [
            'code' => 'TEST',
            'name' => 'Test Leave',
            'is_paid' => true,
            'default_days_per_year' => 10,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->employeeUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(403);
    }

    public function test_leave_type_code_must_be_unique(): void
    {
        LeaveType::factory()->create(['code' => 'ANNUAL']);

        $data = [
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'default_days_per_year' => 12,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_leave_type_name_must_be_unique(): void
    {
        LeaveType::factory()->create(['name' => 'Annual Leave']);

        $data = [
            'code' => 'ANNUAL2',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'default_days_per_year' => 12,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_default_days_cannot_exceed_max_days(): void
    {
        $data = [
            'code' => 'TEST',
            'name' => 'Test Leave',
            'is_paid' => true,
            'max_days_per_year' => 10,
            'default_days_per_year' => 15,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_days_per_year']);
    }

    public function test_max_carry_forward_cannot_exceed_max_days(): void
    {
        $data = [
            'code' => 'TEST',
            'name' => 'Test Leave',
            'is_paid' => true,
            'max_days_per_year' => 10,
            'default_days_per_year' => 10,
            'carry_forward_enabled' => true,
            'max_carry_forward_days' => 15,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_carry_forward_days']);
    }

    public function test_admin_can_view_leave_type(): void
    {
        $leaveType = LeaveType::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/leave-types/{$leaveType->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $leaveType->id,
                ]
            ]);
    }

    public function test_admin_can_update_leave_type(): void
    {
        $leaveType = LeaveType::factory()->create([
            'max_days_per_year' => 30,
            'default_days_per_year' => 20,
        ]);

        $data = [
            'name' => 'Updated Leave Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/leave-types/{$leaveType->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Leave type updated successfully.',
                'data' => [
                    'name' => 'Updated Leave Name',
                ]
            ]);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Updated Leave Name',
        ]);
    }

    public function test_admin_can_delete_leave_type(): void
    {
        $leaveType = LeaveType::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/leave-types/{$leaveType->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('leave_types', ['id' => $leaveType->id]);
    }

    public function test_cannot_delete_leave_type_with_leaves(): void
    {
        $leaveType = LeaveType::factory()->create();
        
        // Create a leave record using factory
        \App\Models\Leave::factory()->create([
            'leave_type_id' => $leaveType->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/leave-types/{$leaveType->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete leave type with existing leaves.',
            ]);
    }

    public function test_cannot_delete_leave_type_with_policies(): void
    {
        $leaveType = LeaveType::factory()->create();
        
        // Create a policy record using factory
        \App\Models\LeavePolicy::factory()->create([
            'leave_type_id' => $leaveType->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/leave-types/{$leaveType->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete leave type with existing policies. Delete policies first.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_leave_types(): void
    {
        $response = $this->getJson('/api/v1/admin/leave-types');

        $response->assertStatus(401);
    }

    public function test_leave_type_code_must_follow_format(): void
    {
        $data = [
            'code' => 'invalid-code',
            'name' => 'Test Leave',
            'is_paid' => true,
            'default_days_per_year' => 10,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_leave_type_code_must_be_uppercase(): void
    {
        $data = [
            'code' => 'annual',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'default_days_per_year' => 10,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_gender_restriction_must_be_valid(): void
    {
        $data = [
            'code' => 'TEST',
            'name' => 'Test Leave',
            'is_paid' => true,
            'default_days_per_year' => 10,
            'gender_restriction' => 'invalid',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/leave-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender_restriction']);
    }
}
