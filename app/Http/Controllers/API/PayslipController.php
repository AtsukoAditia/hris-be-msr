<?php

namespace App\Http\Controllers\API;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\ActivityLog;
use App\Models\Payroll;
use App\Services\PayrollReportingService;
use App\Services\SimplePdfBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayslipController extends Controller
{
    public function __construct(
        private readonly PayrollReportingService $reportingService,
        private readonly SimplePdfBuilder $pdfBuilder,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $request->user()?->employee;
        abort_unless($employee, 403, 'Employee profile is required.');

        $perPage = min(max($request->integer('per_page', 12), 1), 50);
        $payrolls = Payroll::query()
            ->with(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster'])
            ->where('employee_id', $employee->id)
            ->whereIn('status', [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID])
            ->latest('id')
            ->paginate($perPage);

        return PayrollResource::collection($payrolls);
    }

    public function show(Request $request, Payroll $payroll): PayrollResource
    {
        $this->authorizeEmployeePayslip($request, $payroll);

        return new PayrollResource($payroll->load($this->relations()));
    }

    public function download(Request $request, Payroll $payroll): Response
    {
        $this->authorizeEmployeePayslip($request, $payroll);
        $payroll->load($this->relations());
        $content = $this->pdfBuilder->render(
            $this->reportingService->payslipLines($payroll),
            'Employee Payslip',
        );

        ActivityLog::log(ActivityAction::MANUAL_UPDATE, Payroll::class, $payroll->id, [
            'event' => 'export',
            'format' => 'pdf',
            'scope' => 'employee_payslip',
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payslip-'.$payroll->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function authorizeEmployeePayslip(Request $request, Payroll $payroll): void
    {
        $employee = $request->user()?->employee;
        abort_unless($employee && $payroll->employee_id === $employee->id, 403, 'You cannot access this payslip.');
        abort_unless(
            in_array($payroll->status, [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID], true),
            404,
        );
    }

    private function relations(): array
    {
        return [
            'period',
            'employee.user',
            'employee.departmentMaster',
            'employee.positionMaster',
            'items.salaryComponent',
        ];
    }
}
