<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\PayrollReportRequest;
use App\Models\ActivityLog;
use App\Models\Payroll;
use App\Services\PayrollReportingService;
use App\Services\SimplePdfBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PayrollReportAdminController extends Controller
{
    public function __construct(
        private readonly PayrollReportingService $reportingService,
        private readonly SimplePdfBuilder $pdfBuilder,
    ) {}

    public function summary(PayrollReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportingService->summary($request->safe()->except('format')),
        ]);
    }

    public function export(PayrollReportRequest $request): Response
    {
        $filters = $request->safe()->except('format');
        $format = $request->validated('format') ?? 'csv';
        $records = $this->reportingService->query($filters)->get();
        $summary = $this->reportingService->summary($filters);
        $timestamp = now()->format('Ymd-His');

        ActivityLog::log(ActivityAction::MANUAL_UPDATE, Payroll::class, 0, [
            'event' => 'export',
            'format' => $format,
            'filters' => $filters,
            'records_count' => $records->count(),
        ]);

        if ($format === 'pdf') {
            $content = $this->pdfBuilder->render(
                $this->reportingService->reportLines($records, $summary),
                'Payroll Report',
            );

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="payroll-report-'.$timestamp.'.pdf"',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        return response($this->reportingService->csv($records), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="payroll-report-'.$timestamp.'.csv"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadPayslip(Payroll $payroll): Response
    {
        abort_unless(
            in_array($payroll->status, [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID], true),
            409,
            'Only finalized or paid payroll can be downloaded as a payslip.',
        );

        $payroll->load([
            'period',
            'employee.user',
            'employee.departmentMaster',
            'employee.positionMaster',
            'items.salaryComponent',
        ]);
        $content = $this->pdfBuilder->render(
            $this->reportingService->payslipLines($payroll),
            'Employee Payslip',
        );

        ActivityLog::log(ActivityAction::MANUAL_UPDATE, Payroll::class, $payroll->id, [
            'event' => 'export',
            'format' => 'pdf',
            'scope' => 'admin_payslip',
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payslip-'.$payroll->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
