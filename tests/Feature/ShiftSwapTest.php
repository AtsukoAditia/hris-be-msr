<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSwapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private Employee $staffEmployee;
    private Employee $otherEmployee;
    private Shift $shift;
    private ShiftSchedule $requesterSchedule;
    private ShiftSchedule $targetSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->employee = User::factory()->create(['role' => 'employee']);

        $this->staffEmployee = Employee::factory()->create(['user_id' => $this->employee->id]);
        $this->otherEmployee = Employee::factory()->create();

        $this->shift = Shift::factory()->create();

        $this->requesterSchedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-08-01',
            'is_day_off' => false,
        ]);

        $this->targetSchedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->otherEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-08-01',
            'is_day_off' => false,
        ]);
    }

    public function test_employee_can_create_swap_request(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson('/api/v1/shift-swap-requests', [
                'target_id' => $this->otherEmployee->id,
                'requester_schedule_id' => $this->requesterSchedule->id,
                'target_schedule_id' => $this->targetSchedule->id,
                'reason' => 'Test',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('shift_swap_requests', [
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_list_swap_requests(): void
    {
        ShiftSwapRequest::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-swap-requests');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_approve_swap_request(): void
    {
        $swap = ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-swap-requests/{$swap->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('shift_swap_requests', [
            'id' => $swap->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reject_swap_request(): void
    {
        $swap = ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-swap-requests/{$swap->id}/reject", [
                'review_notes' => 'Not approved',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('shift_swap_requests', [
            'id' => $swap->id,
            'status' => 'rejected',
        ]);
    }

    public function test_requester_can_cancel_pending_swap(): void
    {
        $swap = ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson("/api/v1/shift-swap-requests/{$swap->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_approved_swap(): void
    {
        $swap = ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson("/api/v1/shift-swap-requests/{$swap->id}/cancel");

        $response->assertStatus(409);
    }

    public function test_employee_can_view_my_requests(): void
    {
        ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->getJson('/api/v1/shift-swap-requests/my');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_can_view_incoming_requests(): void
    {
        ShiftSwapRequest::factory()->create([
            'requester_id' => $this->otherEmployee->id,
            'target_id' => $this->staffEmployee->id,
            'requester_schedule_id' => $this->targetSchedule->id,
            'target_schedule_id' => $this->requesterSchedule->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->getJson('/api/v1/shift-swap-requests/incoming');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_swap_request_requires_valid_target(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson('/api/v1/shift-swap-requests', [
                'target_id' => 99999,
                'requester_schedule_id' => $this->requesterSchedule->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_swap_executes_on_approval(): void
    {
        $swap = ShiftSwapRequest::factory()->create([
            'requester_id' => $this->staffEmployee->id,
            'target_id' => $this->otherEmployee->id,
            'requester_schedule_id' => $this->requesterSchedule->id,
            'target_schedule_id' => $this->targetSchedule->id,
            'reason' => 'Test',
        ]);

        $originalRequesterShift = $this->requesterSchedule->shift_id;
        $originalTargetShift = $this->targetSchedule->shift_id;

        $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-swap-requests/{$swap->id}/approve");

        $this->requesterSchedule->refresh();
        $this->targetSchedule->refresh();

        $this->assertEquals($originalTargetShift, $this->requesterSchedule->shift_id);
        $this->assertEquals($originalRequesterShift, $this->targetSchedule->shift_id);
    }
}
