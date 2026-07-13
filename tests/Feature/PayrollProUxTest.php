<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProUxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $employee;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
      $this->employee = Employee::factory()->create();
   $this->period = PayrollPeriod::factory()->create(['status' => 'open']);
    }

    public function test_can_lock_payroll_period(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payroll-periods/{$this->period->id}/lock");

        $response->assertOk();
    $this->assertNotNull($this->period->fresh()->locked_at);
        $this->assertEquals($this->admin->id, $this->period->fresh()->locked_by);
  }

    public function test_can_unlock_payroll_period(): void
    {
        $this->period->update(['locked_at' => now(), 'locked_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payroll-periods/{$this->period->id}/unlock");

        $response->assertOk();
        $this->assertNull($this->period->fresh()->locked_at);
        $this->assertNull($this->period->fresh()->locked_by);
    }

    public function test_cannot_unlock_period_with_finalized_payroll(): void
    {
        $this->period->update(['locked_at' => now(), 'locked_by' => $this->admin->id]);
        Payroll::factory()->create([
       'payroll_period_id' => $this->period->id,
         'employee_id' => $this->employee->id,
            'status' => Payroll::STATUS_FINALIZED,
        ]);

 $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payroll-periods/{$this->period->id}/unlock");

 $response->assertStatus(409);
    }

    public function test_can_add_payroll_adjustment(): void
    {
        $payroll = Payroll::factory()->create([
   'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
 'status' => Payroll::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/adjustments", [
       'type' => 'earning',
         'code' => 'BONUS',
         'name' => 'Performance Bonus',
       'amount' => 500000,
            'reason' => 'Q4 performance',
     ]);

        $response->assertCreated();
 $this->assertDatabaseHas('payroll_adjustments', [
   'payroll_id' => $payroll->id,
   'code' => 'BONUS',
 'amount' => 500000,
        ]);
    }

    public function test_cannot_add_adjustment_to_finalized_payroll(): void
    {
      $payroll = Payroll::factory()->create([
            'payroll_period_id' => $this->period->id,
   'employee_id' => $this->employee->id,
  'status' => Payroll::STATUS_FINALIZED,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/adjustments", [
   'type' => 'earning',
            'code' => 'BONUS',
            'name' => 'Bonus',
 'amount' => 100000,
  ]);

     $response->assertStatus(422);
    }

    public function test_can_simulate_payroll(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/payrolls/simulate', [
   'employee_id' => $this->employee->id,
  'payroll_period_id' => $this->period->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
        'data' => [
  'employee_id',
              'period',
         'basic_salary',
     'items',
        'total_earnings',
           'total_deductions',
                'net_salary',
    'simulated',
         ],
      ]);
        $this->assertTrue($response->json('data.simulated'));
    }

    public function test_payroll_approval_workflow(): void
    {
      $payroll = Payroll::factory()->create([
     'payroll_period_id' => $this->period->id,
 'employee_id' => $this->employee->id,
            'status' => Payroll::STATUS_DRAFT,
        ]);

        // Submit
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/submit");
        $response->assertOk();
        $this->assertEquals(Payroll::STATUS_SUBMITTED, $payroll->fresh()->status);

     // Review
  $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/review");
     $response->assertOk();
        $this->assertEquals(Payroll::STATUS_REVIEWED, $payroll->fresh()->status);

        // Approve
 $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/approve");
 $response->assertOk();
        $this->assertEquals(Payroll::STATUS_APPROVED, $payroll->fresh()->status);

   // Finalize
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/payrolls/{$payroll->id}/finalize");
        $response->assertOk();
    $this->assertEquals(Payroll::STATUS_FINALIZED, $payroll->fresh()->status);
    }
}
