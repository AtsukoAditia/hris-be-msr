<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@company.com')->firstOrFail();
        $this->hr = User::where('email', 'hr@company.com')->firstOrFail();
    }

    public function test_executive_summary_returns_all_sections(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/executive-summary')
            ->assertOk()
            ->assertJsonStructure([
                'headcount' => ['total_active', 'new_hires_this_month'],
                'attendance' => ['total_records', 'present', 'late', 'absent', 'attendance_rate', 'total_late_minutes'],
                'leave' => ['total_requests', 'approved', 'pending', 'rejected', 'total_approved_days'],
                'payroll' => ['period', 'total_net', 'total_tax', 'total_bpjs', 'employees_paid'],
            ]);
    }

    public function test_executive_summary_headcount(): void
    {
        $active = Employee::where('is_active', true)->count();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/executive-summary')
            ->assertOk()
            ->assertJsonPath('headcount.total_active', $active);
    }

    public function test_executive_summary_includes_attendance_rates(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/executive-summary')
            ->assertOk()
            ->assertJsonStructure([
                'attendance' => ['total_records', 'present', 'late', 'absent', 'attendance_rate'],
            ]);
    }

    public function test_headcount_by_department(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/headcount')
            ->assertOk()
            ->assertJsonStructure(['data' => [['department', 'count', 'percentage']], 'total']);
    }

    public function test_headcount_percentage_sums_to_100(): void
    {
        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/headcount')
            ->assertOk();

        $data = $res->json();
        $totalPct = collect($data['data'])->sum('percentage');
        $this->assertEqualsWithDelta(100, $totalPct, 1);
    }

    public function test_attendance_summary_by_department(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/attendance-summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['department', 'total_records', 'present_count', 'late_count', 'absent_count']],
            ]);
    }

    public function test_attendance_summary_respects_month_year(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/attendance-summary?month=6&year=2026')
            ->assertOk();
    }

    public function test_analytics_requires_auth(): void
    {
        $this->getJson('/api/v1/analytics/executive-summary')->assertStatus(401);
    }

    public function test_analytics_reject_employee_role(): void
    {
        $emp = User::where('email', 'employee@company.com')->firstOrFail();

        $this->actingAs($emp)
            ->getJson('/api/v1/analytics/executive-summary')
            ->assertStatus(403);
    }

    public function test_analytics_hr_can_access(): void
    {
        $this->actingAs($this->hr)
            ->getJson('/api/v1/analytics/executive-summary')
            ->assertOk();
    }
}
