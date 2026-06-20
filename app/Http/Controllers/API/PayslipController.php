<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use App\Models\Payroll;
use App\Services\PayslipPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayslipController extends Controller
{
    public function __construct(private readonly PayslipPdfService $pdfService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'Employee profile not found.');

        $perPage = min(max($request->integer('per_page', 12), 1), 100);
        $query = Payroll::query()
            ->with(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items'])
            ->where('employee_id', $employee->id)
            ->whereIn('status', [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID]);

        if ($request->filled('year')) {
            $request->validate(['year' => ['integer', 'min:2000', 'max:2100']]);
            $query->whereHas('period', fn ($builder) => $builder->whereYear('start_date', $request->integer('year')));
        }

        return PayslipResource::collection($query->latest('finalized_at')->latest('id')->paginate($perPage));
    }

    public function show(Request $request, Payroll $payroll): PayslipResource
    {
        $payroll = $this->ownedVisiblePayroll($request, $payroll);

        return new PayslipResource($payroll);
    }

    public function download(Request $request, Payroll $payroll): Response
    {
        $payroll = $this->ownedVisiblePayroll($request, $payroll);
        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payroll->employee?->employee_number ?? $payroll->employee_id,
            str($payroll->period?->name ?? $payroll->id)->slug(),
        );

        return response($this->pdfService->generate($payroll), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function ownedVisiblePayroll(Request $request, Payroll $payroll): Payroll
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'Employee profile not found.');
        abort_unless($payroll->employee_id === $employee->id, 403, 'You cannot access another employee payslip.');
        abort_unless(in_array($payroll->status, [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID], true), 404, 'Payslip is not available.');

        return $payroll->load(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items']);
    }
}
