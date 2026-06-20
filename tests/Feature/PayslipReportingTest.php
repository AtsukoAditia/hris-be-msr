<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_own_finalized_or_paid_payslips(): void
    {
        [$employeeUser, $employee, $payroll] = $this->createPayslip(Payroll::STATUS_FINALIZED);
        [, , $otherPayroll] = $this->createPayslip(Payroll::STATUS_PAID, 'July 2026');
        $draft = $this->createPayroll($employee, Payroll::STATUS_DRAFT, 'August 2026');

        $this->actingAs($employeeUser)
            ->getJson('/api/v1/payslips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payroll->id);

        $this->actingAs($employeeUser)
            ->getJson("/api/v1/payslips/{$payroll->id}")
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonCount(3, 'data.items');

        $this->actingAs($employeeUser)
            ->getJson("/api/v1/payslips/{$otherPayroll->id}")
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson("/api/v1/payslips/{$draft->id}")
            ->assertNotFound();
    }

    public function test_authenticated_employee_can_download_own_payslip_pdf(): void
    {
        [$employeeUser, , $payroll] = $this->createPayslip(Payroll::STATUS_PAID);

        $response = $this->actingAs($employeeUser)
            ->get("/api/v1/payslips/{$payroll->id}/download");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'private, no-store, max-age=0');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_admin_and_hr_can_get_period_summary_and_export_csv_and_pdf(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        [, , $payroll] = $this->createPayslip(Payroll::STATUS_FINALIZED);

        $this->actingAs($hr)
            ->getJson("/api/v1/admin/payroll-reports/summary?payroll_period_id={$payroll->payroll_period_id}")
            ->assertOk()
            ->assertJsonPath('data.records_count', 1)
            ->assertJsonPath('data.status_counts.finalized', 1)
            ->assertJsonPath('data.total_net_salary', '9600000.00');

        $csv = $this->actingAs($hr)
            ->get("/api/v1/admin/payroll-reports/export?format=csv&payroll_period_id={$payroll->payroll_period_id}");
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Employee Number', $csv->getContent());
        $this->assertStringContainsString('9600000.00', $csv->getContent());

        $pdf = $this->actingAs($hr)
            ->get("/api/v1/admin/payroll-reports/export?format=pdf&payroll_period_id={$payroll->payroll_period_id}");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
    }

    public function test_manager_cannot_access_payroll_reports_or_admin_payslip_download(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        [, , $payroll] = $this->createPayslip(Payroll::STATUS_FINALIZED);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/payroll-reports/summary')
            ->assertForbidden();

        $this->actingAs($manager)
            ->get("/api/v1/admin/payrolls/{$payroll->id}/payslip/download")
            ->assertForbidden();
    }

    public function test_admin_can_download_only_finalized_or_paid_payslip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $employee] = $this->createPayslip(Payroll::STATUS_FINALIZED);
        $draft = $this->createPayroll($employee, Payroll::STATUS_DRAFT, 'September 2026');

        $this->actingAs($admin)
            ->get("/api/v1/admin/payrolls/{$draft->id}/payslip/download")
            ->assertStatus(409);
    }

    private function createPayslip(string $status, string $periodName = 'June 2026'): array
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'basic_salary' => 9000000,
            'is_active' => true,
        ]);
        $payroll = $this->createPayroll($employee, $status, $periodName);

        return [$user, $employee, $payroll];
    }

    private function createPayroll(Employee $employee, string $status, string $periodName): Payroll
    {
        $month = match (true) {
            str_contains($periodName, 'July') => 7,
            str_contains($periodName, 'August') => 8,
            str_contains($periodName, 'September') => 9,
            default => 6,
        };
        $start = sprintf('2026-%02d-01', $month);
        $end = sprintf('2026-%02d-%02d', $month, $month === 6 ? 30 : 31);
        $period = PayrollPeriod::create([
            'name' => $periodName,
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_start_date' => $start,
            'cutoff_end_date' => $end,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $payroll = Payroll::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => $status,
            'currency' => 'IDR',
            'basic_salary' => 9000000,
            'total_earnings' => 10000000,
            'total_deductions' => 400000,
            'net_salary' => 9600000,
            'attendance_days' => 20,
            'absent_days' => 1,
            'late_minutes' => 10,
            'unpaid_leave_days' => 0,
            'overtime_minutes' => 120,
            'generated_at' => now(),
        ]);
        $payroll->items()->createMany([
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => 'earning', 'source' => 'basic_salary', 'amount' => 9000000],
            ['code' => 'ALLOWANCE', 'name' => 'Allowance', 'type' => 'earning', 'source' => 'salary_component', 'amount' => 1000000],
            ['code' => 'ABSENCE', 'name' => 'Absence Deduction', 'type' => 'deduction', 'source' => 'attendance', 'amount' => 400000],
        ]);

        return $payroll;
    }
}
