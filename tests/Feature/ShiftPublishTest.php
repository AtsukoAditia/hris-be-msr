<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private Employee $staffEmployee;

    private Shift $shift;

    private ShiftSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->staffEmployee = Employee::factory()->create();
        $this->employee = $this->staffEmployee->user;
        $this->employee->role = 'employee';
        $this->employee->save();

        $this->shift = Shift::factory()->create();

        $this->schedule = ShiftSchedule::factory()->create([
            'employee_id' => $this->staffEmployee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-08-01',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_publish_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-schedules/{$this->schedule->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->schedule->refresh();
        $this->assertEquals('published', $this->schedule->status);
        $this->assertNotNull($this->schedule->published_at);
        $this->assertEquals($this->admin->id, $this->schedule->published_by);
    }

    public function test_admin_can_unpublish_schedule(): void
    {
        $this->schedule->update([
            'status' => 'published',
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-schedules/{$this->schedule->id}/unpublish");

        $response->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->schedule->refresh();
        $this->assertEquals('draft', $this->schedule->status);
        $this->assertNull($this->schedule->published_at);
    }

    public function test_publish_increments_version(): void
    {
        $originalVersion = $this->schedule->version;

        $this->actingAs($this->admin)
            ->postJson("/api/v1/shift-schedules/{$this->schedule->id}/publish");

        $this->schedule->refresh();
        $this->assertEquals($originalVersion + 1, $this->schedule->version);
    }

    public function test_employee_cannot_publish_schedule(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson("/api/v1/shift-schedules/{$this->schedule->id}/publish");

        $response->assertForbidden();
    }

    public function test_employee_cannot_unpublish_schedule(): void
    {
        $this->schedule->update([
            'status' => 'published',
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson("/api/v1/shift-schedules/{$this->schedule->id}/unpublish");

        $response->assertForbidden();
    }

    public function test_versions_endpoint_returns_history(): void
    {
        // Create some version records
        $this->schedule->versions()->create([
            'version' => 1,
            'changes' => ['field' => 'shift_id', 'old' => null, 'new' => $this->shift->id],
            'changed_by' => $this->admin->id,
            'action' => 'created',
        ]);

        $this->schedule->versions()->create([
            'version' => 2,
            'changes' => ['field' => 'status', 'old' => 'draft', 'new' => 'published'],
            'changed_by' => $this->admin->id,
            'action' => 'published',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/shift-schedules/{$this->schedule->id}/versions");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_versions_ordered_by_version_desc(): void
    {
        $this->schedule->versions()->create([
            'version' => 1,
            'changes' => ['field' => 'shift_id', 'old' => null, 'new' => $this->shift->id],
            'changed_by' => $this->admin->id,
            'action' => 'created',
        ]);

        $this->schedule->versions()->create([
            'version' => 2,
            'changes' => ['field' => 'status', 'old' => 'draft', 'new' => 'published'],
            'changed_by' => $this->admin->id,
            'action' => 'published',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/shift-schedules/{$this->schedule->id}/versions");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data[0]['version']);
        $this->assertEquals(1, $data[1]['version']);
    }

    public function test_publish_nonexistent_schedule_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/shift-schedules/99999/publish');

        $response->assertNotFound();
    }
}
