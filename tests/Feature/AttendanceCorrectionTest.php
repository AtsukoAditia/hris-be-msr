<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $manager;
    private User $employee;
    private Employee $employeeRecord;
    private Attendance $attendance;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shift = Shift::factory()->create([
            'name' => 'Regular Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance' => 15,
            'is_active' => true,
        ]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->hr = User::factory()->create(['role' => 'hr']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->employee = User::factory()->create(['role' => 'employee']);
        $this->employeeRecord = Employee::factory()->create([
            'user_id' => $this->employee->id,
            'is_active' => true,
        ]);
        $this->attendance = Attendance::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'check_in_time' => now()->subDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'check_out_time' => now()->subDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
            'status' => 'present',
            'late_minutes' => 0,
        ]);
    }

    public function test_employee_can_list_own_corrections(): void
    {
        AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
        ]);

        $response = $this->actingAs($this->employee)->getJson('/api/v1/attendance-corrections/my');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_employee_can_submit_correction(): void
    {
        $response = $this->actingAs($this->employee)->postJson('/api/v1/attendance-corrections', [
            'attendance_date' => $this->attendance->attendance_date->toDateString(),
            'correction_type' => 'check_in',
            'requested_check_in' => '07:55',
            'reason' => 'Terlambat karena macet',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attendance_correction_requests', [
            'employee_id' => $this->employeeRecord->id,
            'correction_type' => 'check_in',
            'status' => 'pending',
        ]);
    }

    public function test_employee_cannot_submit_duplicate_pending(): void
    {
        AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_id' => $this->attendance->id,
            'correction_date' => $this->attendance->attendance_date,
            'correction_type' => 'check_in',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)->postJson('/api/v1/attendance-corrections', [
            'attendance_id' => $this->attendance->id,
            'correction_date' => $this->attendance->attendance_date,
            'correction_type' => 'check_in',
            'requested_check_in' => '07:50',
            'reason' => 'Duplicate test',
        ]);

        $response->assertStatus(422);
    }

    public function test_employee_can_cancel_own_pending(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)->postJson("/api/v1/attendance-corrections/{$correction->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_employee_cannot_approve(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)->postJson("/api/v1/attendance-corrections/{$correction->id}/approve");

        $response->assertStatus(403);
    }

    public function test_hr_can_approve_correction(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_id' => $this->attendance->id,
            'correction_date' => $this->attendance->attendance_date->toDateString(),
            'correction_type' => 'check_in',
            'requested_check_in' => now()->subDay()->setTime(7, 55),
            'requested_check_out' => null,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/approve");

        $response->assertOk();
        $correction->refresh();
        $this->assertEquals('approved', $correction->status);
        // Verify attendance was updated
        $this->attendance->refresh();
        $this->assertEquals('07:55:00', $this->attendance->check_in_time->format('H:i:s'));
    }

    public function test_hr_can_reject_with_note(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/reject", [
            'review_note' => 'Bukti tidak cukup',
        ]);

        $response->assertOk();
        $correction->refresh();
        $this->assertEquals('rejected', $correction->status);
        $this->assertEquals('Bukti tidak cukup', $correction->review_note);
    }

    public function test_reject_requires_note(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/reject");

        $response->assertStatus(422);
    }

    public function test_cannot_approve_already_approved(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_id' => $this->attendance->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/approve");

        $response->assertStatus(422);
    }

    public function test_hr_can_manual_correction(): void
    {
        $response = $this->actingAs($this->hr)->postJson('/api/v1/attendance-corrections/manual', [
            'employee_id' => $this->employeeRecord->id,
            'attendance_date' => $this->attendance->attendance_date->toDateString(),
            'correction_type' => 'check_in',
            'requested_check_in' => '07:50',
            'reason' => 'Manual correction by HR',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attendance_correction_requests', [
            'status' => 'approved',
            'correction_type' => 'check_in',
        ]);
        $this->attendance->refresh();
        $this->assertEquals('07:50:00', $this->attendance->check_in_time->format('H:i:s'));
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/v1/attendance-corrections');
        $response->assertStatus(401);
    }

    public function test_approve_updates_attendance_check_out(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_id' => $this->attendance->id,
            'correction_date' => $this->attendance->attendance_date->toDateString(),
            'correction_type' => 'check_out',
            'requested_check_in' => null,
            'requested_check_out' => now()->subDay()->setTime(18, 30),
            'status' => 'pending',
        ]);

        $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/approve")->assertOk();

        $this->attendance->refresh();
        $this->assertEquals('18:30:00', $this->attendance->check_out_time->format('H:i:s'));
    }

    public function test_approve_updates_both_times(): void
    {
        $correction = AttendanceCorrectionRequest::factory()->create([
            'employee_id' => $this->employeeRecord->id,
            'attendance_id' => $this->attendance->id,
            'correction_date' => $this->attendance->attendance_date->toDateString(),
            'correction_type' => 'both',
            'requested_check_in' => now()->subDay()->setTime(7, 50),
            'requested_check_out' => now()->subDay()->setTime(18, 0),
            'status' => 'pending',
        ]);

        $this->actingAs($this->hr)->postJson("/api/v1/attendance-corrections/{$correction->id}/approve")->assertOk();

        $this->attendance->refresh();
        $this->assertEquals('07:50:00', $this->attendance->check_in_time->format('H:i:s'));
        $this->assertEquals('18:00:00', $this->attendance->check_out_time->format('H:i:s'));
    }
}