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

        return Payroll::create([
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
    }
}
