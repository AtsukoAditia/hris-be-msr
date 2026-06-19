<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $adminEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminEmployee = Employee::factory()->create(['user_id' => $this->admin->id]);
    }

    // ---------------------------------------------------------------
    // Leave Type CRUD
    // ---------------------------------------------------------------

    public function test_admin_can_list_leave_types(): void
    {
        LeaveType::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/leave-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_list_leave_types(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->getJson('/api/v1/admin/leave-types')
            ->assertForbidden();
    }

    public function test_admin_can_create_leave_type(): void
    {
        $payload = [
            'code' => 'AL',
            'name' => 'Annual Leave',
            'description' => 'Paid annual leave',
            'is_paid' => true,
            'requires_attachment' => false,
            'max_consecutive_days' => 14,
            'min_service_months' => 6,
            'gender_restriction' => 'all',
            'requires_balance' => true,
            'max_days_per_year' => 12,
            'default_days_per_year' => 12,
            'carry_forward_enabled' => true,
            'max_carry_forward_days' => 5,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/leave-types', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'AL')
            ->assertJsonPath('data.name', 'Annual Leave');

        $this->assertDatabaseHas('leave_types', ['code' => 'AL', 'name' => 'Annual Leave']);
    }

    public function test_admin_can_update_leave_type(): void
    {
        $leaveType = LeaveType::factory()->create(['code' => 'SL', 'name' => 'Sick Leave']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/leave-types/{$leaveType->id}", [
                'name' => 'Sick Leave Updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Sick Leave Updated');
    }

    public function test_admin_can_delete_leave_type(): void
    {
        $leaveType = LeaveType::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/leave-types/{$leaveType->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('leave_types', ['id' => $leaveType->id]);
    }

    // ---------------------------------------------------------------
    // Leave Policy CRUD
    // ---------------------------------------------------------------

    public function test_admin_can_list_leave_policies(): void
    {
        LeavePolicy::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/leave-policies');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_leave_policy(): void
    {
        $leaveType = LeaveType::factory()->create();

        $payload = [
            'leave_type_id' => $leaveType->id,
            'year' => (int) date('Y'),
            'policy_name' => 'Standard Annual Policy',
            'default_quota' => 12,
            'min_service_months' => 0,
            'accrual_type' => 'yearly',
            'max_carry_forward_days' => 5,
            'carry_forward_expiry_month' => 3,
            'carry_forward_expiry_day' => 31,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/leave-policies', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.policy_name', 'Standard Annual Policy')
            ->assertJsonPath('data.default_quota', 12);

        $this->assertDatabaseHas('leave_policies', [
            'leave_type_id' => $leaveType->id,
            'policy_name' => 'Standard Annual Policy',
        ]);
    }

    public function test_admin_can_update_leave_policy(): void
    {
        $policy = LeavePolicy::factory()->create([
            'policy_name' => 'Old Policy',
            'default_quota' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/leave-policies/{$policy->id}", [
                'policy_name' => 'New Policy',
                'default_quota' => 15,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.policy_name', 'New Policy')
            ->assertJsonPath('data.default_quota', 15);
    }

    public function test_admin_can_delete_leave_policy(): void
    {
        $policy = LeavePolicy::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/leave-policies/{$policy->id}")
            ->assertOk();

        $this->assertDatabaseMissing('leave_policies', ['id' => $policy->id]);
    }

    // ---------------------------------------------------------------
    // Holiday CRUD
    // ---------------------------------------------------------------

    public function test_admin_can_list_holidays(): void
    {
        Holiday::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/holidays');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_holiday(): void
    {
        $payload = [
            'name' => 'Independence Day',
            'date' => '2026-08-17',
            'type' => 'national',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/holidays', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Independence Day');

        $this->assertDatabaseHas('holidays', ['name' => 'Independence Day']);
    }

    public function test_admin_can_update_holiday(): void
    {
        $holiday = Holiday::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/holidays/{$holiday->id}", [
                'name' => 'Updated Holiday',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Holiday');
    }

    public function test_admin_can_delete_holiday(): void
    {
        $holiday = Holiday::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/holidays/{$holiday->id}")
            ->assertOk();

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    // ---------------------------------------------------------------
    // Leave Balance CRUD
    // ---------------------------------------------------------------

    public function test_admin_can_list_leave_balances(): void
    {
        LeaveBalance::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/leave-balances');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'leave_type_id',
                        'year',
                        'total_days',
                        'used_days',
                        'pending_days',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_leave_balance(): void
    {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        $payload = [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => (int) date('Y'),
            'total_days' => 12,
            'used_days' => 0,
            'pending_days' => 0,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/leave-balances', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.total_days', 12);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => (int) date('Y'),
        ]);
    }

    public function test_admin_can_update_leave_balance(): void
    {
        $balance = LeaveBalance::factory()->create(['total_days' => 12, 'used_days' => 2]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/leave-balances/{$balance->id}", [
                'total_days' => 15,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_days', 15);
    }

    public function test_admin_can_delete_leave_balance(): void
    {
        $balance = LeaveBalance::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/leave-balances/{$balance->id}")
            ->assertOk();

        $this->assertDatabaseMissing('leave_balances', ['id' => $balance->id]);
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    public function test_create_leave_type_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/leave-types', ['code' => 'XX'])
            ->assertUnprocessable();
    }

    public function test_create_leave_policy_requires_leave_type_id(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/leave-policies', [
                'policy_name' => 'Test',
                'year' => 2026,
                'default_quota' => 10,
            ])
            ->assertUnprocessable();
    }

    public function test_create_holiday_requires_date(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/holidays', ['name' => 'Test Holiday'])
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Employee access denied
    // ---------------------------------------------------------------

    public function test_employee_cannot_manage_leave_types(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/leave-types')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/leave-types', ['code' => 'X', 'name' => 'Test'])
            ->assertForbidden();
    }

    public function test_employee_cannot_manage_leave_policies(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/leave-policies')
            ->assertForbidden();
    }

    public function test_employee_cannot_manage_holidays(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/holidays')
            ->assertForbidden();
    }

    public function test_employee_cannot_manage_leave_balances(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/leave-balances')
            ->assertForbidden();
    }
}
