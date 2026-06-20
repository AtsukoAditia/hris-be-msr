<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $hr;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Activity Admin']);
        $this->hr = User::factory()->create(['role' => 'hr']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->employee = User::factory()->create(['role' => 'employee']);
    }

    private function createLogs(int $count = 3): void
    {
        ActivityLog::factory($count)->create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
            'user_role' => 'admin',
            'module' => 'employee',
            'action' => 'create',
        ]);
    }

    public function test_admin_can_list_activity_logs(): void
    {
        $this->createLogs(3);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.total', 3);
    }

    public function test_hr_can_list_activity_logs(): void
    {
        $this->createLogs(2);

        $response = $this->actingAs($this->hr)->getJson('/api/v1/activity-logs');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 2);
    }

    public function test_manager_cannot_list_activity_logs(): void
    {
        $this->createLogs(1);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/activity-logs');

        $response->assertForbidden();
    }

    public function test_employee_cannot_list_activity_logs(): void
    {
        $this->createLogs(1);

        $response = $this->actingAs($this->employee)->getJson('/api/v1/activity-logs');

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_activity_logs(): void
    {
        $response = $this->getJson('/api/v1/activity-logs');

        $response->assertUnauthorized();
    }

    public function test_admin_can_view_single_log(): void
    {
        $log = ActivityLog::factory()->create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
            'user_role' => 'admin',
            'module' => 'employee',
            'action' => 'update',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/activity-logs/{$log->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_employee_cannot_view_single_log(): void
    {
        $log = ActivityLog::factory()->create([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ]);

        $response = $this->actingAs($this->employee)->getJson("/api/v1/activity-logs/{$log->id}");

        $response->assertForbidden();
    }

    public function test_list_returns_404_for_nonexistent_log(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs/99999');

        $response->assertNotFound();
    }

    public function test_filter_by_module(): void
    {
        ActivityLog::factory()->create([
            'module' => 'attendance',
            'action' => 'create',
            'user_role' => 'admin',
        ]);
        ActivityLog::factory()->create([
            'module' => 'leave',
            'action' => 'create',
            'user_role' => 'admin',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?module=attendance');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_by_action(): void
    {
        ActivityLog::factory()->create(['action' => 'create', 'user_role' => 'admin']);
        ActivityLog::factory()->create(['action' => 'delete', 'user_role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?action=create');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_by_user_role(): void
    {
        ActivityLog::factory()->create(['user_role' => 'admin']);
        ActivityLog::factory()->create(['user_role' => 'employee']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?user_role=admin');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_by_date_range(): void
    {
        ActivityLog::factory()->create([
            'logged_at' => now()->subDays(5),
            'user_role' => 'admin',
        ]);
        ActivityLog::factory()->create([
            'logged_at' => now()->subDays(1),
            'user_role' => 'admin',
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/api/v1/activity-logs?date_from='.now()->subDays(3)->toDateString()
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_search_filter(): void
    {
        ActivityLog::factory()->create([
            'user_name' => 'John Doe',
            'description' => 'Created employee record',
            'user_role' => 'admin',
        ]);
        ActivityLog::factory()->create([
            'user_name' => 'Jane Smith',
            'description' => 'Updated leave request',
            'user_role' => 'admin',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?search=John');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_invalid_role_filter_rejected(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?user_role=invalid');

        $response->assertUnprocessable();
    }

    public function test_pagination(): void
    {
        ActivityLog::factory(20)->create(['user_role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs?per_page=5');

        $response->assertOk()
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 20);
    }
}
