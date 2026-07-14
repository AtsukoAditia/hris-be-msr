<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\BankExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankExportController extends Controller
{
    public function __construct(private readonly BankExportService $exportService) {}

    /**
     * Preview bank transfer data for a payroll period.
     * GET /api/v1/admin/payroll-periods/{period}/bank-export
     */
    public function preview(PayrollPeriod $period): JsonResponse
    {
        $payrolls = Payroll::with('employee')
            ->where('payroll_period_id', $period->id)
            ->where('status', 'finalized')
            ->whereNotNull('bank_account_number')
            ->orderBy('employee_id')
            ->get();

        $rows = $payrolls->map(fn (Payroll $p, int $i) => [
            'no' => $i + 1,
            'employee_id' => $p->employee_id,
            'employee_name' => $p->employee?->full_name ?? '-',
            'bank_account_number' => $p->bank_account_number,
            'bank_name' => $p->bank_name,
            'bank_account_name' => $p->bank_account_name,
            'net_salary' => number_format((float) $p->net_salary, 0, ',', '.'),
            'net_salary_raw' => (float) $p->net_salary,
        ]);

        return response()->json([
            'columns' => $this->exportService->columns(),
            'data' => $rows,
            'total' => $rows->sum('net_salary_raw'),
            'count' => $rows->count(),
        ]);
    }

    /**
     * Download bank transfer file.
     * GET /api/v1/admin/payroll-periods/{period}/bank-export/download
     */
    public function download(PayrollPeriod $period, Request $request)
    {
        $payrolls = Payroll::with('employee')
            ->where('payroll_period_id', $period->id)
            ->where('status', 'finalized')
            ->whereNotNull('bank_account_number')
            ->orderBy('employee_id')
            ->get();

        return $this->exportService->generate(
            $period,
            $payrolls,
            $request->input('format', 'csv'),
        );
    }
}
