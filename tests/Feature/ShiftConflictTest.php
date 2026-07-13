<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $staffEmployee;

    private Shift $shift;

    private Shift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->staffEmployee = Employee::factory()->create();

        $this->shift = Shift::factory()->create([
            'name' => 'Morning Shift',
            'code' => 'SH-MOR',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_overnight' => false,
        ]);

        $this->nightShift = Shift::factory()->create([
            'name' => 'Night Shift',
            'code' => 'SH-NIT',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_overnight' => true,
        ]);
    }

    public function test_validate_conflicts_returns_empty_when_no_conflicts(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 0);
    }

    public function test_validate_conflicts_with_employee_filter(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-08-01',
            'is_day_off' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'employee_id' => $this->staffEmployee->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_validate_conflicts_requires_date_range(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', []);

        $response->assertStatus(422);
    }

    public function test_validate_conflicts_requires_valid_date_range(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-31',
                'end_date' => '2026-08-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_rest_hour_conflict_detected(): void
    {
        // Create two consecutive shifts with only 8h gap
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id, // 08:00-17:00
            'schedule_date' => '2026-08-01',
            'is_day_off' => false,
        ]);

        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->nightShift->id, // 22:00-06:00 next day
            'schedule_date' => '2026-08-02',
            'is_day_off' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-02',
                'employee_id' => $this->staffEmployee->id,
            ]);

        $response->assertOk();
        $conflicts = $response->json('conflicts');
        $restHourConflicts = array_filter($conflicts, fn($c) => $c['type'] === 'rest_hour');
        $this->assertNotEmpty($restHourConflicts);
    }

    public function test_max_hours_conflict_detected(): void
    {
        // Create 5 days of 8h shifts in one week = 40h exactly OK, 6th day = 48h
        for ($i = 0; $i < 6; $i++) {
            ShiftSchedule::factory()->create([
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => "2026-08-0" . ($i + 1),
                'is_day_off' => false,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-06',
                'employee_id' => $this->staffEmployee->id,
            ]);

        $response->assertOk();
        $conflicts = $response->json('conflicts');
        $maxHourConflicts = array_filter($conflicts, fn($c) => $c['type'] === 'max_hours');
        $this->assertNotEmpty($maxHourConflicts);
    }

    public function test_overlap_conflict_not_detected_for_single_schedule(): void
    {
        // Single schedule should not trigger overlap
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-08-01',
            'is_day_off' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-01',
                'employee_id' => $this->staffEmployee->id,
            ]);

        $response->assertOk();
        $conflicts = $response->json('conflicts');
        $overlapConflicts = array_filter($conflicts, fn($c) => $c['type'] === 'overlap');
        $this->assertEmpty($overlapConflicts);
    }

    public function test_manager_can_validate_conflicts(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/shift-schedules/validate-conflicts', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]);

        $response->assertOk();
    }
}
