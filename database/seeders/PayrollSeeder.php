<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $employees = Employee::limit(5)->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No employees found. Skipping PayrollSeeder.');
            return;
        }

        // Create payroll periods
        $period1 = PayrollPeriod::factory()->create([
            'name' => 'January 2026 Payroll',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'cutoff_start_date' => '2026-01-01',
            'cutoff_end_date' => '2026-01-31',
            'status' => 'closed',
        ]);

        $period2 = PayrollPeriod::factory()->create([
            'name' => 'February 2026 Payroll',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'cutoff_start_date' => '2026-02-01',
            'cutoff_end_date' => '2026-02-28',
            'status' => 'open',
        ]);

        $period3 = PayrollPeriod::factory()->locked()->create([
            'name' => 'March 2026 Payroll',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'cutoff_start_date' => '2026-03-01',
            'cutoff_end_date' => '2026-03-31',
            'status' => 'open',
        ]);

        // Create payrolls for period 1 (finalized)
        foreach ($employees as $employee) {
            $payroll = Payroll::factory()->finalized()->create([
                'payroll_period_id' => $period1->id,
                'employee_id' => $employee->id,
                'basic_salary' => 5000000,
                'total_earnings' => 5500000,
                'total_deductions' => 500000,
                'net_salary' => 5000000,
            ]);

            // Add adjustments
            PayrollAdjustment::factory()->earning()->create([
                'payroll_id' => $payroll->id,
                'code' => 'BONUS',
                'name' => 'Performance Bonus',
                'amount' => 500000,
                'reason' => 'Q1 performance bonus',
                'created_by' => $admin?->id,
            ]);
        }

        // Create payrolls for period 2 (draft/submitted mix)
        foreach ($employees->take(3) as $index => $employee) {
            $status = $index === 0 ? 'draft' : ($index === 1 ? 'submitted' : 'reviewed');
            $factoryMethod = $status === 'draft' ? null : ($status === 'submitted' ? 'submitted' : 'reviewed');

            $payroll = Payroll::factory()->create([
                'payroll_period_id' => $period2->id,
                'employee_id' => $employee->id,
                'status' => $status,
                'basic_salary' => 5000000,
                'total_earnings' => 5200000,
                'total_deductions' => 200000,
                'net_salary' => 5000000,
            ]);

            if ($factoryMethod) {
                $payroll->update([
                    'submitted_by' => $admin?->id,
                    'submitted_at' => now()->subHours($index),
                ]);
            }

            if ($status === 'reviewed') {
                $payroll->update([
                    'reviewed_by' => $admin?->id,
                    'reviewed_at' => now(),
                ]);
            }

            // Add some adjustments
            if ($index === 0) {
                PayrollAdjustment::factory()->earning()->create([
                    'payroll_id' => $payroll->id,
                    'code' => 'ALLOWANCE',
                    'name' => 'Transport Allowance',
                    'amount' => 200000,
                    'reason' => 'Monthly transport',
                    'created_by' => $admin?->id,
                ]);
            }
        }

        $this->command->info('Payroll data seeded successfully.');
    }
}
