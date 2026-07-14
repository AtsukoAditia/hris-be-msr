<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@company.com')->firstOrFail();
        $this->employee = User::where('email', 'employee@company.com')->firstOrFail();
    }

    public function test_activity_logs_table_has_change_columns(): void
    {
        $log = ActivityLog::create([
            'user_id' => $this->admin->id,
            'user_name' => 'Admin',
            'module' => 'employee',
            'action' => 'update',
            'method' => 'PATCH',
            'endpoint' => 'api/v1/employees/1',
            'response_status' => 200,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'target_type' => 'Employee',
            'target_id' => 1,
            'description' => 'Test update',
            'logged_at' => now(),
        ]);

        $this->assertNotNull($log->old_values);
        $this->assertNotNull($log->new_values);
        $this->assertEquals('Employee', $log->target_type);
        $this->assertEquals(1, $log->target_id);
    }

    public function test_get_diff_lines_returns_correct_changes(): void
    {
        $log = ActivityLog::create([
            'user_id' => $this->admin->id,
            'user_name' => 'Admin',
            'module' => 'employee',
            'action' => 'update',
            'method' => 'PATCH',
            'endpoint' => 'api/v1/employees/1',
            'response_status' => 200,
            'old_values' => ['name' => 'Andi', 'department_id' => 1],
            'new_values' => ['name' => 'Andi Pratama', 'department_id' => 2],
            'target_type' => 'Employee',
            'target_id' => 1,
            'description' => 'Test',
            'logged_at' => now(),
        ]);

        $diff = $log->getDiffLines();
        $this->assertCount(2, $diff);
        $this->assertEquals('name', $diff[0]['field']);
        $this->assertEquals('Andi', $diff[0]['old']);
        $this->assertEquals('Andi Pratama', $diff[0]['new']);
    }

    public function test_get_diff_lines_skips_unchanged_fields(): void
    {
        $log = ActivityLog::create([
            'user_id' => $this->admin->id,
            'user_name' => 'Admin',
            'module' => 'employee',
            'action' => 'update',
            'method' => 'PATCH',
            'endpoint' => 'api/v1/employees/1',
            'response_status' => 200,
            'old_values' => ['name' => 'Same', 'phone' => '081'],
            'new_values' => ['name' => 'Same', 'phone' => '082'],
            'target_type' => 'Employee',
            'target_id' => 1,
            'description' => 'Test',
            'logged_at' => now(),
        ]);

        $diff = $log->getDiffLines();
        $this->assertCount(1, $diff);
        $this->assertEquals('phone', $diff[0]['field']);
    }

    public function test_audit_trail_service_record_change(): void
    {
        $emp = Employee::firstOrFail();

        AuditTrailService::recordChange(
            'employee',
            $emp,
            ['name' => 'Old Name', 'phone' => '081'],
            ['name' => 'New Name', 'phone' => '082'],
            'update',
            'Employee profile updated',
        );

        $this->assertDatabaseHas('activity_logs', [
            'target_type' => 'Employee',
            'target_id' => $emp->id,
            'action' => 'update',
        ]);
    }

    public function test_audit_trail_service_no_change_skips(): void
    {
        $emp = Employee::firstOrFail();

        $log = AuditTrailService::recordChange(
            'employee',
            $emp,
            ['name' => 'Same'],
            ['name' => 'Same'],
            'update',
        );

        $this->assertNull($log);
    }

    public function test_get_trail_endpoint(): void
    {
        $emp = Employee::firstOrFail();

        // Create some logs
        AuditTrailService::recordChange('employee', $emp, ['name' => 'A'], ['name' => 'B'], 'update');
        AuditTrailService::recordChange('employee', $emp, ['name' => 'B'], ['name' => 'C'], 'update');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/audit-trail/employee/{$emp->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_audit_trail_index_filterable(): void
    {
        $emp = Employee::firstOrFail();
        AuditTrailService::recordChange('employee', $emp, ['name' => 'A'], ['name' => 'B'], 'update');
        AuditTrailService::recordChange('leave', $emp, ['status' => 'pending'], ['status' => 'approved'], 'approve');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-trail?module=employee')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-trail')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_employee_update_creates_audit_log(): void
    {
        $emp = Employee::firstOrFail();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/employees/{$emp->id}", [
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_type' => 'Employee',
            'target_id' => $emp->id,
            'action' => 'update',
        ]);
    }

    public function test_field_label_mapping(): void
    {
        $this->assertEquals('Nama Lengkap', ActivityLog::fieldLabel('name'));
        $this->assertEquals('Email', ActivityLog::fieldLabel('email'));
        $this->assertEquals('Departemen', ActivityLog::fieldLabel('department_id'));
        $this->assertEquals('Status Aktif', ActivityLog::fieldLabel('is_active'));
    }

    public function test_audit_trail_requires_auth(): void
    {
        $this->getJson('/api/v1/audit-trail')->assertStatus(401);
    }

    public function test_audit_trail_only_admin_hr(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/audit-trail')
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-trail')
            ->assertOk();
    }
}
