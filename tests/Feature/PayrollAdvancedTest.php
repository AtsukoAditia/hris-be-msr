<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\BpjsCalculator;
use App\Services\Pph21Calculator;
use App\Services\BankExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@company.com')->firstOrFail();
    }

    public function test_bpjs_calculator_rates(): void
    {
        $calc = new BpjsCalculator;
        $result = $calc->calculate(10_000_000);

        $this->assertEqualsWithDelta(10_000_000 * 0.0024, $result['bpjs_jkk'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.003, $result['bpjs_jkm'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.037, $result['bpjs_jht_er'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.02, $result['bpjs_jp_er'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.02, $result['bpjs_jht_ee'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.01, $result['bpjs_jp_ee'], 1);
        $this->assertEqualsWithDelta(10_000_000 * 0.01, $result['bpjs_kes_ee'], 1);
    }

    public function test_bpjs_salary_cap(): void
    {
        $calc = new BpjsCalculator;
        $result = $calc->calculate(20_000_000); // above cap

        // JHT salary cap = 11_934_000
        $this->assertEqualsWithDelta(11_934_000 * 0.02, $result['bpjs_jht_ee'], 1);
        // KES salary cap = 12_000_000
        $this->assertEqualsWithDelta(12_000_000 * 0.01, $result['bpjs_kes_ee'], 1);
    }

    public function test_bpjs_total_methods(): void
    {
        $calc = new BpjsCalculator;
        $employer = $calc->totalEmployerContribution(10_000_000);
        $employee = $calc->totalEmployeeDeduction(10_000_000);

        $this->assertGreaterThan(0, $employer);
        $this->assertGreaterThan(0, $employee);
        $this->assertLessThan($employer, $employee); // employer > employee (JKK + JKM + JHT_ER + JP_ER > JHT_EE + JP_EE + KES_EE)
    }

    public function test_pph21_below_ptkp(): void
    {
        $calc = new Pph21Calculator;
        $result = $calc->calculate(3_000_000 * 12, 'TK0'); // 36M annual, below 54M PTKP

        $this->assertEquals(0, $result['pph21_annual']);
        $this->assertEquals(0, $result['pph21_monthly']);
    }

    public function test_pph21_above_ptkp(): void
    {
        $calc = new Pph21Calculator;
        $result = $calc->calculate(10_000_000 * 12, 'TK0'); // 120M annual

        $this->assertGreaterThan(0, $result['pph21_annual']);
        $this->assertGreaterThan(0, $result['pph21_monthly']);
        $this->assertGreaterThan(0, $result['effective_rate']);
    }

    public function test_pph21_npwp_penalty(): void
    {
        $calc = new Pph21Calculator;
        $withNpwp = $calc->calculate(15_000_000 * 12, 'TK0', true);
        $withoutNpwp = $calc->calculate(15_000_000 * 12, 'TK0', false);

        $this->assertGreaterThan($withNpwp['pph21_annual'], $withoutNpwp['pph21_annual']);
    }

    public function test_pph21_kawin_higher_ptkp(): void
    {
        $calc = new Pph21Calculator;
        $single = $calc->calculate(8_000_000 * 12, 'TK0');
        $married = $calc->calculate(8_000_000 * 12, 'K0');

        // Married has higher PTKP = less PKP = less tax
        $this->assertGreaterThanOrEqual($married['pph21_annual'], $single['pph21_annual']);
    }

    public function test_payroll_model_has_bpjs_fields(): void
    {
        $payroll = Payroll::factory()->create([
            'bpjs_jkk' => 24_000,
            'bpjs_jkm' => 30_000,
            'bpjs_jht_ee' => 200_000,
            'pph21' => 500_000,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'John Doe',
        ]);

        $this->assertEquals(24_000, $payroll->bpjs_jkk);
        $this->assertEquals(500_000, $payroll->pph21);
        $this->assertEquals('BCA', $payroll->bank_name);
    }

    public function test_bank_export_preview(): void
    {
        $period = PayrollPeriod::where('status', 'closed')->first();

        Payroll::factory()->count(3)->create([
            'payroll_period_id' => $period->id,
            'status' => 'finalized',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test User',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/payroll-periods/{$period->id}/bank-export")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['columns', 'data', 'total', 'count']);
    }

    public function test_bank_export_requires_auth(): void
    {
        $period = PayrollPeriod::first();
        $this->getJson("/api/v1/admin/payroll-periods/{$period->id}/bank-export")->assertStatus(401);
    }

    public function test_bank_export_employee_cannot_access(): void
    {
        $emp = User::where('email', 'employee@company.com')->firstOrFail();
        $period = PayrollPeriod::first();

        $this->actingAs($emp)
            ->getJson("/api/v1/admin/payroll-periods/{$period->id}/bank-export")
            ->assertStatus(403);
    }

    public function test_bank_export_download_csv(): void
    {
        $period = PayrollPeriod::where('status', 'closed')->first();

        Payroll::factory()->count(2)->create([
            'payroll_period_id' => $period->id,
            'status' => 'finalized',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test User',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/payroll-periods/{$period->id}/bank-export/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
