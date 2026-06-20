<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function hrUser(): User
    {
        return User::factory()->create(['role' => 'hr']);
    }

    private function managerUser(): User
    {
        $user = User::factory()->create(['role' => 'manager']);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function employeeUser(?int $managerId = null): User
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create([
            'user_id' => $user->id,
            'manager_id' => $managerId,
        ]);

        return $user;
    }

    private function activePolicy(): OvertimePolicy
    {
        return OvertimePolicy::factory()->create([
            'is_active' => true,
            'daily_max_minutes' => 240,
            'weekly_max_minutes' => 1200,
            'rate_multiplier' => 1.5,
        ]);
    }

    // ========================
    // Overtime Policy Admin
    // ========================

    public function test_admin_can_create_overtime_policy(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/overtime-policies', [
            'name' => 'Weekday OT',
            'description' => 'Standard weekday overtime',
            'daily_max_minutes' => 180,
            'weekly_max_minutes' => 900,
            'rate_multiplier' => 1.5,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Weekday OT')
            ->assertJsonPath('data.rate_multiplier', '1.5');
    }

    public function test_employee_cannot_create_overtime_policy(): void
    {
        $employee = $this->employeeUser();

        $this->actingAs($employee)->postJson('/api/v1/admin/overtime-policies', [
            'name' => 'OT',
            'daily_max_minutes' => 120,
            'weekly_max_minutes' => 600,
            'rate_multiplier' => 1.5,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_admin_can_list_overtime_policies(): void
    {
        $admin = $this->adminUser();
        OvertimePolicy::factory()->count(3)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/overtime-policies')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_update_overtime_policy(): void
    {
        $admin = $this->adminUser();
        $policy = $this->activePolicy();

        $this->actingAs($admin)->putJson("/api/v1/admin/overtime-policies/{$policy->id}", [
            'name' => 'Updated OT',
            'daily_max_minutes' => 200,
            'weekly_max_minutes' => 1000,
            'rate_multiplier' => 2.0,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Updated OT');
    }

    public function test_admin_cannot_delete_policy_with_requests(): void
    {
        $admin = $this->adminUser();
        $policy = $this->activePolicy();
        $employee = $this->employeeUser();

        OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/overtime-policies/{$policy->id}")
            ->assertConflict();
    }

    // ========================
    // Submit Overtime Request
    // ========================

    public function test_employee_can_submit_overtime_request(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $response = $this->actingAs($employee)->postJson('/api/v1/overtime-requests', [
            'overtime_policy_id' => $policy->id,
            'overtime_date' => '2026-06-20',
            'planned_start_time' => '18:00',
            'planned_end_time' => '20:00',
            'reason' => 'Project deadline',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OvertimeRequest::STATUS_PENDING)
            ->assertJsonPath('data.planned_minutes', 120);
    }

    public function test_unauthenticated_user_cannot_submit_overtime(): void
    {
        $policy = $this->activePolicy();

        $this->postJson('/api/v1/overtime-requests', [
            'overtime_policy_id' => $policy->id,
            'overtime_date' => '2026-06-20',
            'planned_start_time' => '18:00',
            'planned_end_time' => '20:00',
            'reason' => 'Test',
        ])->assertUnauthorized();
    }

    public function test_overtime_request_requires_reason(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $this->actingAs($employee)->postJson('/api/v1/overtime-requests', [
            'overtime_policy_id' => $policy->id,
            'overtime_date' => '2026-06-20',
            'planned_start_time' => '18:00',
            'planned_end_time' => '20:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    // ========================
    // Approve / Reject
    // ========================

    public function test_manager_can_approve_subordinate_overtime(): void
    {
        $manager = $this->managerUser();
        $employee = $this->employeeUser($manager->employee->id);
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->postJson("/api/v1/overtime-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', OvertimeRequest::STATUS_APPROVED);
    }

    public function test_employee_cannot_approve_own_overtime(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($employee)->postJson("/api/v1/overtime-requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_manager_can_reject_overtime_with_reason(): void
    {
        $manager = $this->managerUser();
        $employee = $this->employeeUser($manager->employee->id);
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->postJson("/api/v1/overtime-requests/{$request->id}/reject", [
            'rejection_reason' => 'Not needed',
        ])->assertOk()
            ->assertJsonPath('data.status', OvertimeRequest::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Not needed');
    }

    public function test_cannot_approve_already_approved_request(): void
    {
        $admin = $this->adminUser();
        $policy = $this->activePolicy();
        $emp = $this->employeeUser();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $emp->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/overtime-requests/{$request->id}/approve")
            ->assertForbidden();
    }

    // ========================
    // Cancel
    // ========================

    public function test_employee_can_cancel_own_pending_overtime(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($employee)->postJson("/api/v1/overtime-requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OvertimeRequest::STATUS_CANCELLED);
    }

    public function test_employee_cannot_cancel_approved_overtime(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)->postJson("/api/v1/overtime-requests/{$request->id}/cancel")
            ->assertForbidden();
    }

    // ========================
    // Record Actual Minutes
    // ========================

    public function test_admin_can_record_actual_minutes(): void
    {
        $admin = $this->adminUser();
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/overtime-requests/{$request->id}/record-actual", [
            'actual_minutes' => 110,
        ])->assertOk()
            ->assertJsonPath('data.actual_minutes', 110);
    }

    public function test_employee_cannot_record_actual_minutes(): void
    {
        $employee = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
            'status' => OvertimeRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)->postJson("/api/v1/overtime-requests/{$request->id}/record-actual", [
            'actual_minutes' => 90,
        ])->assertForbidden();
    }

    // ========================
    // Visibility / Ownership
    // ========================

    public function test_employee_can_only_see_own_overtime_requests(): void
    {
        $employee = $this->employeeUser();
        $other = $this->employeeUser();
        $policy = $this->activePolicy();

        OvertimeRequest::factory()->count(2)->create([
            'employee_id' => $employee->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);
        OvertimeRequest::factory()->create([
            'employee_id' => $other->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);

        $response = $this->actingAs($employee)->getJson('/api/v1/overtime-requests/my');
        $response->assertOk();
        // Employee should only see their own 2 requests
        $this->assertCount(2, $response->json('data'));
    }

    public function test_employee_cannot_view_another_employees_overtime(): void
    {
        $employee = $this->employeeUser();
        $other = $this->employeeUser();
        $policy = $this->activePolicy();

        $request = OvertimeRequest::factory()->create([
            'employee_id' => $other->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);

        $this->actingAs($employee)->getJson("/api/v1/overtime-requests/{$request->id}")
            ->assertForbidden();
    }

    public function test_admin_can_see_all_overtime_requests(): void
    {
        $admin = $this->adminUser();
        $emp1 = $this->employeeUser();
        $emp2 = $this->employeeUser();
        $policy = $this->activePolicy();

        OvertimeRequest::factory()->count(3)->create([
            'employee_id' => $emp1->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);
        OvertimeRequest::factory()->count(2)->create([
            'employee_id' => $emp2->employee->id,
            'overtime_policy_id' => $policy->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/overtime-requests');
        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }
}
