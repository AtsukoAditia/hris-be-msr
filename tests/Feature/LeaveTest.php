<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $employeeUser;

    protected User $hrUser;

    protected User $managerUser;

    protected Employee $employee;

    protected LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->employeeUser = User::factory()->create([
            'role' => 'employee',
        ]);
        $this->employee = Employee::factory()->create([
            'user_id' => $this->employeeUser->id,
            'join_date' => now()->subYear(),
            'gender' => 'male',
        ]);

        $this->hrUser = User::factory()->create(['role' => 'hr']);

        $this->managerUser = User::factory()->create([
            'role' => 'manager',
        ]);

        $this->leaveType = LeaveTypeFactory::new()->create([
            'is_active' => true,
            'requires_balance' => true,
            'requires_attachment' => false,
            'max_days_per_year' => 12,
            'gender_restriction' => 'all',
        ]);
    }

    public function test_employee_can_request_leave(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'reason' => 'Keperluan keluarga',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'status', 'total_days']]);

        $this->assertDatabaseHas('leaves', [
            'employee_id' => $this->employee->id,
            'status' => 'pending',
            'total_days' => 3,
        ]);
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leave_type_id', 'start_date', 'end_date', 'reason']);
    }

    public function test_validation_fails_when_end_date_before_start_date(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_validation_fails_when_dates_exceed_max_days(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(100)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_validation_fails_when_service_months_less_than_3(): void
    {
        $newUser = User::factory()->create(['role' => 'employee']);
        $newEmployee = Employee::factory()->create([
            'user_id' => $newUser->id,
            'join_date' => now()->subMonth(),
        ]);

        Sanctum::actingAs($newUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_validation_fails_for_gender_restricted_leave(): void
    {
        $maternityLeave = LeaveTypeFactory::new()->create([
            'is_active' => true,
            'gender_restriction' => 'female',
            'requires_balance' => false,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $maternityLeave->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leave_type_id']);
    }

    public function test_validation_fails_for_inactive_leave_type(): void
    {
        $inactiveType = LeaveTypeFactory::new()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $inactiveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leave_type_id']);
    }

    public function test_unauthenticated_user_cannot_request_leave(): void
    {
        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'reason' => 'Keperluan',
        ]);

        $response->assertUnauthorized();
    }

    public function test_employee_can_view_own_leaves(): void
    {
        Leave::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->getJson('/api/v1/leaves/my');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_employee_cannot_access_admin_leaves_index(): void
    {
        $otherUser = User::factory()->create(['role' => 'employee']);
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);
        Leave::factory()->create([
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
        ]);

        Sanctum::actingAs($this->employeeUser);

        // Admin/HR/manager-only index must reject regular employees.
        $response = $this->getJson('/api/v1/leaves?employee_id='.$otherEmployee->id);

        $response->assertForbidden();
    }

    public function test_hr_can_view_all_leaves(): void
    {
        Sanctum::actingAs($this->hrUser);

        $response = $this->getJson('/api/v1/leaves');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_hr_can_approve_pending_leave(): void
    {
        $leave = Leave::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_PENDING,
            'total_days' => 3,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(9),
        ]);

        Sanctum::actingAs($this->hrUser);

        $response = $this->postJson("/api/v1/leaves/{$leave->id}/approve", [
            'note' => 'Disetujui',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => Leave::STATUS_APPROVED,
        ]);
    }

    public function test_hr_can_reject_pending_leave(): void
    {
        $leave = Leave::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->hrUser);

        $response = $this->postJson("/api/v1/leaves/{$leave->id}/reject", [
            'rejection_reason' => 'Tidak memenuhi syarat minimal masa kerja.',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => Leave::STATUS_REJECTED,
            'rejection_reason' => 'Tidak memenuhi syarat minimal masa kerja.',
        ]);
    }

    public function test_cannot_approve_already_approved_leave(): void
    {
        $leave = Leave::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_APPROVED,
        ]);

        Sanctum::actingAs($this->hrUser);

        $response = $this->postJson("/api/v1/leaves/{$leave->id}/approve", [
            'note' => 'Coba approve lagi',
        ]);

        $response->assertStatus(422);
    }

    public function test_employee_can_cancel_own_pending_leave(): void
    {
        $leave = Leave::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->deleteJson("/api/v1/leaves/{$leave->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => Leave::STATUS_CANCELLED,
        ]);
    }

    public function test_employee_cannot_cancel_others_pending_leave(): void
    {
        $otherUser = User::factory()->create(['role' => 'employee']);
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);
        $leave = Leave::factory()->create([
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $this->leaveType->id,
            'leave_type' => $this->leaveType->code,
            'status' => Leave::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->deleteJson("/api/v1/leaves/{$leave->id}");

        $response->assertForbidden();
    }

    public function test_employee_can_view_leave_types(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->getJson('/api/v1/leave-types');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->leaveType->id, $ids);
    }

    public function test_leave_types_endpoint_hides_inactive_types_for_employees(): void
    {
        LeaveTypeFactory::new()->create(['is_active' => false]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->getJson('/api/v1/leave-types');

        $response->assertOk();

        $isActives = collect($response->json('data'))->pluck('is_active')->unique()->toArray();
        $this->assertEquals([true], $isActives);
    }

    public function test_leave_balance_endpoint(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->getJson('/api/v1/leaves/balance');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_leave_with_attachment_upload(): void
    {
        $attachmentType = LeaveTypeFactory::new()->create([
            'is_active' => true,
            'requires_attachment' => true,
            'requires_balance' => false,
            'gender_restriction' => 'all',
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type_id' => $attachmentType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'reason' => 'Keperluan medis',
            'attachment' => UploadedFile::fake()->create('medical.pdf', 1024),
        ]);

        $response->assertCreated();

        $leave = Leave::where('employee_id', $this->employee->id)->latest()->first();
        $this->assertNotNull($leave->attachment);
    }
}
