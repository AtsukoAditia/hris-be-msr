<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\PayrollMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_manage_salary_components_and_manager_cannot_access_them(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/salary-components')
            ->assertForbidden();

        $hr = User::factory()->create(['role' => 'hr']);

        $response = $this->actingAs($hr)
            ->postJson('/api/v1/admin/salary-components', [
                'code' => ' transport ',
                'name' => 'Transport Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 500000,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.code', 'TRANSPORT')
            ->assertJsonPath('data.default_amount', '500000.00');

        $componentId = $response->json('data.id');

        $this->actingAs($hr)
            ->putJson("/api/v1/admin/salary-components/{$componentId}", [
                'code' => 'TRANSPORT',
                'name' => 'Monthly Transport Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 600000,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Monthly Transport Allowance');
    }

    public function test_hr_can_create_effective_salary_profile_with_components_and_overlap_is_rejected(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = Employee::factory()->create(['basic_salary' => 5000000]);
        $component = SalaryComponent::create([
            'code' => 'MEAL',
            'name' => 'Meal Allowance',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_amount' => 300000,
            'is_active' => true,
        ]);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/employees/{$employee->id}/salary-profiles", [
                'basic_salary' => 10000000,
                'currency' => 'idr',
                'effective_from' => '2026-06-01',
                'components' => [
                    ['salary_component_id' => $component->id, 'amount' => 400000],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.basic_salary', '10000000.00')
            ->assertJsonPath('data.components.0.salary_component.code', 'MEAL');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'basic_salary' => 10000000,
        ]);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/employees/{$employee->id}/salary-profiles", [
                'basic_salary' => 11000000,
                'currency' => 'IDR',
                'effective_from' => '2026-06-15',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['effective_from']);
    }

    public function test_payroll_generation_uses_salary_attendance_unpaid_leave_and_approved_overtime_inputs(): void
    {
        [$hr, $employee, $period] = $this->createPayrollScenario();

        $response = $this->actingAs($hr)
            ->postJson("/api/v1/admin/payroll-periods/{$period->id}/generate", [
                'employee_ids' => [$employee->id],
            ])
            ->assertOk()
            ->assertJsonPath('meta.generated_count', 1)
            ->assertJsonPath('data.0.status', Payroll::STATUS_DRAFT)
            ->assertJsonPath('data.0.attendance_days', 1)
            ->assertJsonPath('data.0.absent_days', 1)
            ->assertJsonPath('data.0.late_minutes', 15)
            ->assertJsonPath('data.0.unpaid_leave_days', 2)
            ->assertJsonPath('data.0.overtime_minutes', 60);

        $payrollId = $response->json('data.0.id');
        $itemCodes = collect($response->json('data.0.items'))->pluck('code');

        $this->assertTrue($itemCodes->contains('BASIC'));
        $this->assertTrue($itemCodes->contains('TRANSPORT'));
        $this->assertTrue($itemCodes->contains('PERFORMANCE'));
        $this->assertTrue($itemCodes->contains('OVERTIME'));
        $this->assertTrue($itemCodes->contains('ABSENCE'));
        $this->assertTrue($itemCodes->contains('UNPAID_LEAVE'));
        $this->assertGreaterThan(0, (float) $response->json('data.0.net_salary'));

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'status' => Payroll::STATUS_DRAFT,
        ]);
    }

    public function test_payroll_status_workflow_is_locked_after_finalization_and_can_be_marked_paid(): void
    {
        [$hr, $employee, $period] = $this->createPayrollScenario();

        $payrollId = $this->actingAs($hr)
            ->postJson("/api/v1/admin/payroll-periods/{$period->id}/generate", [
                'employee_ids' => [$employee->id],
            ])
            ->json('data.0.id');

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', Payroll::STATUS_REVIEWED);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', Payroll::STATUS_FINALIZED);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/recalculate")
            ->assertStatus(409);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/paid")
            ->assertOk()
            ->assertJsonPath('data.status', Payroll::STATUS_PAID);

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/cancel", ['reason' => 'Cannot cancel paid'])
            ->assertStatus(409);
    }

    public function test_draft_payroll_can_be_cancelled_with_a_reason(): void
    {
        [$hr, $employee, $period] = $this->createPayrollScenario();

        $payrollId = $this->actingAs($hr)
            ->postJson("/api/v1/admin/payroll-periods/{$period->id}/generate", [
                'employee_ids' => [$employee->id],
            ])
            ->json('data.0.id');

        $this->actingAs($hr)
            ->postJson("/api/v1/admin/payrolls/{$payrollId}/cancel", ['reason' => 'Incorrect payroll cutoff'])
            ->assertOk()
            ->assertJsonPath('data.status', Payroll::STATUS_CANCELLED)
            ->assertJsonPath('data.cancellation_reason', 'Incorrect payroll cutoff');
    }

    public function test_payroll_money_uses_integer_safe_rounding(): void
    {
        $this->assertSame(1000000000, PayrollMoney::toCents('10000000.00'));
        $this->assertSame('10000000.00', PayrollMoney::fromCents(1000000000));
        $this->assertSame(100000000, PayrollMoney::percentage(1000000000, '10.0000'));
        $this->assertSame(45454545, PayrollMoney::ratio(1000000000, 1, 22));
    }

    private function createPayrollScenario(): array
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employeeUser = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'basic_salary' => 10000000,
            'is_active' => true,
        ]);

        $transport = SalaryComponent::create([
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_amount' => 500000,
            'is_active' => true,
        ]);
        $performance = SalaryComponent::create([
            'code' => 'PERFORMANCE',
            'name' => 'Performance Allowance',
            'type' => 'earning',
            'calculation_type' => 'percentage',
            'default_amount' => 0,
            'percentage' => 10,
            'is_active' => true,
        ]);

        $profile = $employee->salaryProfiles()->create([
            'basic_salary' => 10000000,
            'currency' => 'IDR',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
        $profile->components()->createMany([
            ['salary_component_id' => $transport->id, 'amount' => 500000],
            ['salary_component_id' => $performance->id, 'percentage' => 10],
        ]);

        $period = PayrollPeriod::create([
            'name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'cutoff_start_date' => '2026-06-01',
            'cutoff_end_date' => '2026-06-25',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-02',
            'status' => 'late',
            'late_minutes' => 15,
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-03',
            'status' => 'absent',
        ]);

        $unpaidType = LeaveType::create([
            'code' => 'UNPAID',
            'name' => 'Unpaid Leave',
            'is_paid' => false,
            'requires_balance' => false,
            'is_active' => true,
        ]);
        Leave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $unpaidType->id,
            'leave_type' => 'UNPAID',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-11',
            'total_days' => 2,
            'reason' => 'Personal leave',
            'status' => Leave::STATUS_APPROVED,
        ]);

        $policy = OvertimePolicy::create([
            'name' => 'Weekday Overtime',
            'daily_max_minutes' => 240,
            'weekly_max_minutes' => 720,
            'rate_multiplier' => 1.5,
            'is_active' => true,
        ]);
        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'overtime_policy_id' => $policy->id,
            'approved_by' => $hr->id,
            'overtime_date' => '2026-06-12',
            'planned_start_time' => '18:00',
            'planned_end_time' => '19:00',
            'planned_minutes' => 60,
            'actual_minutes' => 60,
            'rate_multiplier' => 1.5,
            'status' => OvertimeRequest::STATUS_APPROVED,
            'reason' => 'Release support',
            'approved_at' => now(),
        ]);

        return [$hr, $employee, $period];
    }
}
