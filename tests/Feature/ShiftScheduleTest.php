<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $manager;
    private User $employee;
    private Employee $managerEmployee;
    private Employee $staffEmployee;
    private Shift $shift;
    private Shift $shift2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->managerEmployee = Employee::factory()->create();
        $this->manager = $this->managerEmployee->user;
        $this->manager->role = 'manager';
        $this->manager->save();

        $this->staffEmployee = Employee::factory()->create(['manager_id' => $this->managerEmployee->id]);
        $this->employee = $this->staffEmployee->user;
        $this->employee->role = 'employee';
        $this->employee->save();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->hr = User::factory()->create(['role' => 'hr']);

        $this->shift = Shift::factory()->create([
            'name' => 'Morning Shift',
            'code' => 'SH-MOR',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_overnight' => false,
        ]);

        $this->shift2 = Shift::factory()->create([
            'name' => 'Night Shift',
            'code' => 'SH-NIT',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_overnight' => true,
        ]);
    }

    // ── Index ──

    public function test_admin_can_list_schedules(): void
    {
        ShiftSchedule::factory()->count(3)->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'employee', 'shift', 'schedule_date', 'is_day_off']]]);
    }

    public function test_filter_by_date_range(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-01',
        ]);
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-15',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules?start_date=2026-07-01&end_date=2026-07-07');

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertEquals(1, $data->count());
        $this->assertEquals('2026-07-01', $data->first()['schedule_date']);
    }

    public function test_filter_by_employee_id(): void
    {
        $otherEmployee = Employee::factory()->create();
        ShiftSchedule::factory()->create(['employee_id' => $this->staffEmployee->id, 'shift_id' => $this->shift->id]);
        ShiftSchedule::factory()->create(['employee_id' => $otherEmployee->id, 'shift_id' => $this->shift->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules?employee_id='.$this->staffEmployee->id);

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertEquals(1, $data->count());
    }

    public function test_filter_by_shift_id(): void
    {
        ShiftSchedule::factory()->create(['employee_id' => $this->staffEmployee->id, 'shift_id' => $this->shift->id]);
        ShiftSchedule::factory()->create(['employee_id' => $this->staffEmployee->id, 'shift_id' => $this->shift2->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules?shift_id='.$this->shift->id);

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertEquals(1, $data->count());
    }

    public function test_filter_by_is_day_off(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'is_day_off' => false,
        ]);
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => null,
            'is_day_off' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules?is_day_off=1');

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertEquals(1, $data->count());
        $this->assertTrue($data->first()['is_day_off']);
    }

    public function test_unauthenticated_cannot_list(): void
    {
        $response = $this->getJson('/api/v1/shift-schedules');
        $response->assertUnauthorized();
    }

    // ── Store (single) ──

    public function test_admin_can_create_single_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules', [
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => '2026-07-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee.id', $this->staffEmployee->id)
            ->assertJsonPath('data.shift.id', $this->shift->id);
        $this->assertDatabaseCount('shift_schedules', 1);
    }

    public function test_admin_can_create_day_off(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules', [
                'employee_id' => $this->staffEmployee->id,
                'schedule_date' => '2026-07-01',
                'is_day_off' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_day_off', true)
            ->assertJsonPath('data.shift', null);
    }

    public function test_single_schedule_conflict_validation(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules', [
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => '2026-07-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_employee_cannot_create_schedule(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson('/api/v1/shift-schedules', [
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => '2026-07-01',
            ]);

        $response->assertForbidden();
    }

    // ── Bulk Store ──

    public function test_admin_can_bulk_assign(): void
    {
        $payload = [
            'employee_ids' => [$this->staffEmployee->id],
            'schedules' => [
                ['shift_id' => $this->shift->id, 'date' => '2026-07-01'],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-02'],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-03'],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/bulk', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseCount('shift_schedules', 3);
    }

    public function test_bulk_assign_with_day_off(): void
    {
        $payload = [
            'employee_ids' => [$this->staffEmployee->id],
            'schedules' => [
                ['shift_id' => $this->shift->id, 'date' => '2026-07-01'],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-02'],
                ['date' => '2026-07-03', 'is_day_off' => true],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-04'],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-05'],
                ['date' => '2026-07-06', 'is_day_off' => true],
                ['date' => '2026-07-07', 'is_day_off' => true],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/bulk', $payload);

        $response->assertStatus(201);
        $schedules = ShiftSchedule::all();
        $this->assertCount(3, $schedules->where('is_day_off', true));
        $this->assertCount(4, $schedules->where('is_day_off', false));
    }

    public function test_bulk_assign_conflict_partial_success(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-01',
        ]);

        $payload = [
            'employee_ids' => [$this->staffEmployee->id],
            'schedules' => [
                ['shift_id' => $this->shift->id, 'date' => '2026-07-01'],
                ['shift_id' => $this->shift->id, 'date' => '2026-07-02'],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/bulk', $payload);

        $response->assertStatus(207);
        $this->assertCount(1, $response->json('data'));
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_bulk_assign_validation_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/bulk', [
                'employee_ids' => [],
                'schedules' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_employee_cannot_bulk_assign(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson('/api/v1/shift-schedules/bulk', [
                'employee_ids' => [$this->staffEmployee->id],
                'schedules' => [
                    ['shift_id' => $this->shift->id, 'date' => '2026-07-01'],
                ],
            ]);

        $response->assertForbidden();
    }

    // ── Copy Week ──

    public function test_admin_can_copy_week(): void
    {
        $dates = ['2026-06-29', '2026-06-30', '2026-07-01', '2026-07-02', '2026-07-03'];
        foreach ($dates as $date) {
            ShiftSchedule::factory()->create([
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => $date,
                'is_day_off' => false,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/copy-week', [
                'source_start_date' => '2026-06-29',
                'target_start_date' => '2026-07-06',
            ]);

        $response->assertSuccessful();
        $targetCount = ShiftSchedule::whereBetween('schedule_date', ['2026-07-06', '2026-07-12'])->count();
        $this->assertEquals(5, $targetCount);
    }

    public function test_copy_week_with_employee_filter(): void
    {
        $otherEmployee = Employee::factory()->create();
        $dates = ['2026-06-29', '2026-06-30', '2026-07-01'];
        foreach ($dates as $date) {
            ShiftSchedule::factory()->create([
                'employee_id' => $this->staffEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => $date,
            ]);
            ShiftSchedule::factory()->create([
                'employee_id' => $otherEmployee->id,
                'shift_id' => $this->shift->id,
                'schedule_date' => $date,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/copy-week', [
                'source_start_date' => '2026-06-29',
                'target_start_date' => '2026-07-06',
                'employee_ids' => [$this->staffEmployee->id],
            ]);

        $response->assertSuccessful();
        $this->assertEquals(3, ShiftSchedule::whereBetween('schedule_date', ['2026-07-06', '2026-07-12'])
            ->where('employee_id', $this->staffEmployee->id)->count());
        $this->assertEquals(0, ShiftSchedule::whereBetween('schedule_date', ['2026-07-06', '2026-07-12'])
            ->where('employee_id', $otherEmployee->id)->count());
    }

    // ── My Schedule (employee) ──

    public function test_employee_can_view_own_schedule(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->employee)
            ->getJson('/api/v1/shift-schedules/my-schedule?start_date=2026-07-01&end_date=2026-07-07');

        $response->assertOk()
            ->assertJsonPath('data.0.shift.name', 'Morning Shift');
    }

    // ── Team Schedule (manager) ──

    public function test_manager_can_view_team_schedule(): void
    {
        ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/shift-schedules/team-schedule?start_date=2026-07-01&end_date=2026-07-07');

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertTrue($data->contains('employee.id', $this->staffEmployee->id));
    }

    public function test_employee_cannot_view_team_schedule(): void
    {
        $response = $this->actingAs($this->employee)
            ->getJson('/api/v1/shift-schedules/team-schedule');

        $response->assertForbidden();
    }

    // ── Show ──

    public function test_admin_can_show_schedule(): void
    {
        $schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules/'.$schedule->id);

        $response->assertOk()
            ->assertJsonPath('data.employee.id', $this->staffEmployee->id)
            ->assertJsonPath('data.shift.id', $this->shift->id);
    }

    public function test_not_found_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/shift-schedules/999');

        $response->assertNotFound();
    }

    // ── Update ──

    public function test_admin_can_update_schedule(): void
    {
        $schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/shift-schedules/'.$schedule->id, [
                'shift_id' => $this->shift2->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.shift.id', $this->shift2->id);
    }

    public function test_admin_can_mark_as_day_off(): void
    {
        $schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/shift-schedules/'.$schedule->id, [
                'is_day_off' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_day_off', true)
            ->assertJsonPath('data.shift', null);
    }

    // ── Delete ──

    public function test_admin_can_delete_schedule(): void
    {
        $schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/v1/shift-schedules/'.$schedule->id);

        $response->assertOk();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);
    }

    public function test_employee_cannot_delete_schedule(): void
    {
        $schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->deleteJson('/api/v1/shift-schedules/'.$schedule->id);

        $response->assertForbidden();
    }

    // ── Permission summary ──

    public function test_roles_permissions(): void
    {
        // Admin can bulk assign
        $this->actingAs($this->admin)->postJson('/api/v1/shift-schedules/bulk', [
            'employee_ids' => [$this->staffEmployee->id],
            'schedules' => [
                ['shift_id' => $this->shift->id, 'date' => '2026-08-01'],
            ],
        ])->assertStatus(201);

        // HR can bulk assign
        $this->actingAs($this->hr)->postJson('/api/v1/shift-schedules/bulk', [
            'employee_ids' => [$this->staffEmployee->id],
            'schedules' => [
                ['shift_id' => $this->shift->id, 'date' => '2026-08-02'],
            ],
        ])->assertStatus(201);

        // Manager can view team
        $this->actingAs($this->manager)
            ->getJson('/api/v1/shift-schedules/team-schedule?start_date=2026-07-01&end_date=2026-07-07')
            ->assertOk();

        // Employee can view own
        $this->actingAs($this->employee)
            ->getJson('/api/v1/shift-schedules/my-schedule?start_date=2026-07-01&end_date=2026-07-07')
            ->assertOk();
    }
}