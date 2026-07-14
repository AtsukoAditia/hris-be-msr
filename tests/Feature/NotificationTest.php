<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    public function test_list_requires_auth(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_list_returns_empty_when_no_notifications(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJson(['data' => [], 'unread_count' => 0]);
    }

    public function test_create_and_list_notifications(): void
    {
        \App\Services\NotificationService::create(
            $this->employee->id,
            'leave_approved',
            'Cuti Disetujui',
            'Cuti tahun disetujui.',
            '✅',
            '/leave',
        );

        $this->actingAs($this->employee)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Cuti Disetujui')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_unread_count(): void
    {
        \App\Services\NotificationService::create($this->employee->id, 'type1', 'Title 1');
        \App\Services\NotificationService::create($this->employee->id, 'type2', 'Title 2');

        $this->actingAs($this->employee)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['unread_count' => 2]);
    }

    public function test_mark_single_as_read(): void
    {
        $n = \App\Services\NotificationService::create($this->employee->id, 'test', 'Test');
        $this->assertFalse($n->fresh()->is_read);

        $this->actingAs($this->employee)
            ->postJson("/api/v1/notifications/{$n->id}/read")
            ->assertOk();

        $this->assertTrue($n->fresh()->is_read);
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        \App\Services\NotificationService::create($this->employee->id, 't1', 'A');
        \App\Services\NotificationService::create($this->employee->id, 't2', 'B');
        \App\Services\NotificationService::create($this->employee->id, 't3', 'C');

        $this->actingAs($this->employee)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertEquals(0, Notification::where('user_id', $this->employee->id)->unread()->count());
    }

    public function test_mark_read_other_user_forbidden(): void
    {
        $n = \App\Services\NotificationService::create($this->employee->id, 'test', 'Secret');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/notifications/{$n->id}/read")
            ->assertNotFound();
    }

    public function test_filter_unread_only(): void
    {
        $n1 = \App\Services\NotificationService::create($this->employee->id, 't1', 'Unread');
        $n2 = \App\Services\NotificationService::create($this->employee->id, 't2', 'Read');
        \App\Services\NotificationService::markRead($this->employee->id, $n2->id);

        $this->actingAs($this->employee)
            ->getJson('/api/v1/notifications?unread_only=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_leave_approval_creates_notification(): void
    {
        $emp = Employee::where('user_id', $this->employee->id)->firstOrFail();
        $leave = \App\Models\Leave::factory()->create([
            'employee_id' => $emp->id,
            'status' => 'pending',
            'leave_type_id' => \App\Models\LeaveType::first()?->id ?? \App\Models\LeaveType::factory(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/leaves/{$leave->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->id,
            'type' => 'leave_approved',
        ]);
    }

    public function test_leave_rejection_creates_notification(): void
    {
        $emp = Employee::where('user_id', $this->employee->id)->firstOrFail();
        $leave = \App\Models\Leave::factory()->create([
            'employee_id' => $emp->id,
            'status' => 'pending',
            'leave_type_id' => \App\Models\LeaveType::first()?->id ?? \App\Models\LeaveType::factory(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/leaves/{$leave->id}/reject", ['rejection_reason' => 'Staf kurang'])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->id,
            'type' => 'leave_rejected',
        ]);
    }

    public function test_notification_service_count_unread(): void
    {
        \App\Services\NotificationService::create($this->employee->id, 't', 'A');
        \App\Services\NotificationService::create($this->employee->id, 't', 'B');
        $n3 = \App\Services\NotificationService::create($this->employee->id, 't', 'C');
        \App\Services\NotificationService::markRead($this->employee->id, $n3->id);

        $this->assertEquals(2, \App\Services\NotificationService::countUnread($this->employee->id));
    }

    public function test_notification_service_mark_all_read(): void
    {
        \App\Services\NotificationService::create($this->employee->id, 't', 'A');
        \App\Services\NotificationService::create($this->employee->id, 't', 'B');

        \App\Services\NotificationService::markAllRead($this->employee->id);
        $this->assertEquals(0, \App\Services\NotificationService::countUnread($this->employee->id));
    }

    public function test_notify_multiple(): void
    {
        $users = User::limit(3)->pluck('id')->toArray();
        \App\Services\NotificationService::notifyMultiple($users, 'broadcast', 'Pengumuman', 'Testing');

        foreach ($users as $uid) {
            $this->assertDatabaseHas('notifications', ['user_id' => $uid, 'type' => 'broadcast']);
        }
    }

    public function test_notification_has_data_payload(): void
    {
        \App\Services\NotificationService::create(
            $this->employee->id,
            'payroll_ready',
            'Slip Gaji Tersedia',
            'Slip gaji Juli 2026 sudah tersedia.',
            '💰',
            '/payslips',
            ['payroll_period_id' => 1],
        );

        $this->actingAs($this->employee)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data', ['payroll_period_id' => 1]);
    }
}
