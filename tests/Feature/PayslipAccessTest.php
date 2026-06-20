<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_own_visible_payslips(): void
    {
        [$user, $employee] = $this->employeeUser('owner@example.com');
        [, $otherEmployee] = $this->employeeUser('other@example.com');
        $visible = $this->payroll($employee, 'paid', 6);
        $draft = $this->payroll($employee, 'draft', 7);
        $other = $this->payroll($otherEmployee, 'paid', 8);

        $response = $this->actingAs($user)->getJson('/api/v1/payslips')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($draft->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_payslip_detail_rejects_draft_and_other_employee(): void
    {
        [$user, $employee] = $this->employeeUser('detail-owner@example.com');
        [, $otherEmployee] = $this->employeeUser('detail-other@example.com');
        $visible = $this->payroll($employee, 'finalized', 6);
        $draft = $this->payroll($employee, 'draft', 7);
        $other = $this->payroll($otherEmployee, 'paid', 8);

        $this->actingAs($user)
            ->getJson("/api/v1/payslips/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.employee.employee_number', $employee->employee_number)
            ->assertJsonMissingPath('data.input_snapshot');

        $this->actingAs($user)->getJson("/api/v1/payslips/{$draft->id}")->assertNotFound();
        $this->actingAs($user)->getJson("/api/v1/payslips/{$other->id}")->assertForbidden();
    }

    public function test_admin_report_returns_summary_and_component_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        [, $employee] = $this->employeeUser('report@example.com');
        $paid = $this->payroll($employee, 'paid', 6);
        $this->payroll($employee, 'finalized', 7);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/payroll-reports?status=paid')
            ->assertOk()
            ->assertJsonPath('summary.total_records', 1)
            ->assertJsonPath('summary.status_counts.paid', 1)
            ->assertJsonPath('summary.by_currency.0.currency', 'IDR')
            ->assertJsonPath('summary.by_currency.0.net_salary', '90.00');

        $this->assertSame($paid->id, $response->json('data.0.id'));

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/payroll-reports/breakdown?status=paid')
            ->assertOk()
            ->assertJsonFragment(['code' => 'BASIC', 'total_amount' => '100.00'])
            ->assertJsonFragment(['code' => 'ABSENCE', 'total_amount' => '10.00']);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/payroll-reports')
            ->assertForbidden();
    }

    private function employeeUser(string $email): array
    {
        $user = User::factory()->create(['email' => $email, 'role' => 'employee']);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return [$user, $employee];
    }

    private function payroll(Employee $employee, string $status, int $month): Payroll
    {
        $period = PayrollPeriod::create([
            'name' => 'Period '.$month,
            'start_date' => sprintf('2026-%02d-01', $month),
            'end_date' => sprintf('2026-%02d-28', $month),
            'cutoff_start_date' => sprintf('2026-%02d-01', $month),
            'cutoff_end_date' => sprintf('2026-%02d-25', $month),
            'status' => 'closed',
        ]);
        $payroll = Payroll::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => $status,
            'currency' => 'IDR',
            'basic_salary' => 100,
            'total_earnings' => 100,
            'total_deductions' => 10,
            'net_salary' => 90,
            'generated_at' => now(),
            'finalized_at' => in_array($status, ['finalized', 'paid'], true) ? now() : null,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
        $payroll->items()->createMany([
            [
                'code' => 'BASIC',
                'name' => 'Basic Salary',
                'type' => 'earning',
                'source' => 'basic_salary',
                'quantity' => 1,
                'rate' => 100,
                'amount' => 100,
            ],
            [
                'code' => 'ABSENCE',
                'name' => 'Absence Deduction',
                'type' => 'deduction',
                'source' => 'attendance',
                'quantity' => 1,
                'rate' => 10,
                'amount' => 10,
            ],
        ]);

        return $payroll;
    }
}
