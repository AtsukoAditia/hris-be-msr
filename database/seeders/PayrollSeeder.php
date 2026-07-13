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
        $period1 = PayrollPeriod::firstOrCreate(
            ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'],
            [
                'name' => 'January 2026 Payroll',
                'cutoff_start_date' => '2026-01-01',
                'cutoff_end_date' => '2026-01-31',
                'status' => 'closed',
            ]
        );

        $period2 = PayrollPeriod::firstOrCreate(
            ['start_date' => '2026-02-01', 'end_date' => '2026-02-28'],
            [
                'name' => 'February 2026 Payroll',
                'cutoff_start_date' => '2026-02-01',
                'cutoff_end_date' => '2026-02-28',
                'status' => 'open',
            ]
        );

        $period3 = PayrollPeriod::firstOrCreate(
            ['start_date' => '2026-03-01', 'end_date' => '2026-03-31'],
            [
                'name' => 'March 2026 Payroll',
                'cutoff_start_date' => '2026-03-01',
                'cutoff_end_date' => '2026-03-31',
                'status' => 'open',
                'locked_at' => now(),
                'locked_by' => $admin?->id,
            ]
        );

        // Create payrolls for period 1 (finalized)
        foreach ($employees as $employee) {
            $payroll = Payroll::firstOrCreate(
                ['payroll_period_id' => $period1->id, 'employee_id' => $employee->id],
                [
                    'status' => Payroll::STATUS_FINALIZED,
                    'basic_salary' => 5000000,
                    'total_earnings' => 5500000,
                    'total_deductions' => 500000,
                    'net_salary' => 5000000,
                    'finalized_by' => $admin?->id,
                    'finalized_at' => now()->subDays(5),
                    'submitted_by' => $admin?->id,
                    'submitted_at' => now()->subDays(7),
                    'reviewed_by' => $admin?->id,
                    'reviewed_at' => now()->subDays(6),
                    'approved_by' => $admin?->id,
                    'approved_at' => now()->subDays(5),
                ]
            );

            // Add adjustment if not exists
            PayrollAdjustment::firstOrCreate(
                ['payroll_id' => $payroll->id, 'code' => 'BONUS'],
                [
                    'type' => 'earning',
                    'name' => 'Performance Bonus',
                    'amount' => 500000,
                    'reason' => 'Q1 performance bonus',
                    'created_by' => $admin?->id,
                ]
            );
        }

        // Create payrolls for period 2 (draft/submitted mix)
        foreach ($employees->take(3) as $index => $employee) {
            $status = $index === 0 ? Payroll::STATUS_DRAFT : ($index === 1 ? Payroll::STATUS_SUBMITTED : Payroll::STATUS_REVIEWED);
            $payroll = Payroll::firstOrCreate(
                ['payroll_period_id' => $period2->id, 'employee_id' => $employee->id],
                [
                    'status' => $status,
                    'basic_salary' => 5000000,
                    'total_earnings' => 5200000,
                    'total_deductions' => 200000,
                    'net_salary' => 5000000,
                    'submitted_by' => $status !== Payroll::STATUS_DRAFT ? $admin?->id : null,
                    'submitted_at' => $status !== Payroll::STATUS_DRAFT ? now()->subHours($index + 1) : null,
                    'reviewed_by' => $status === Payroll::STATUS_REVIEWED ? $admin?->id : null,
                    'reviewed_at' => $status === Payroll::STATUS_REVIEWED ? now()->subHours(1) : null,
                ]
            );

            // Add allowance for first employee
            if ($index === 0) {
                PayrollAdjustment::firstOrCreate(
                    ['payroll_id' => $payroll->id, 'code' => 'ALLOWANCE'],
                    [
                        'type' => 'earning',
                        'name' => 'Transport Allowance',
                        'amount' => 200000,
                        'reason' => 'Monthly transport',
                        'created_by' => $admin?->id,
                    ]
                );
            }
        }

        $this->command->info('Payroll data seeded successfully.');
    }
}
