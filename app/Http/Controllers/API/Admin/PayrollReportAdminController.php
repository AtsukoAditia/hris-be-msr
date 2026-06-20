<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\PayslipPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportAdminController extends Controller
{
    public function __construct(private readonly PayslipPdfService $pdfService) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildQuery($request);
        $summaryQuery = clone $query;
        $perPage = min(max($request->integer('per_page', 30), 1), 100);
        $payrolls = $query
            ->with(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items'])
            ->latest('payrolls.id')
            ->paginate($perPage);

        return response()->json([
            'data' => $this->resourceCollection($payrolls->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $payrolls->currentPage(),
                'last_page' => $payrolls->lastPage(),
                'per_page' => $payrolls->perPage(),
                'total' => $payrolls->total(),
            ],
            'summary' => $this->summary($summaryQuery),
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        $payrollIds = $this->buildQuery($request)->select('payrolls.id');
        $rows = PayrollItem::query()
            ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
            ->whereIn('payroll_items.payroll_id', $payrollIds)
            ->selectRaw('payrolls.currency, payroll_items.code, payroll_items.name, payroll_items.type, COUNT(*) as payroll_count, SUM(payroll_items.amount) as total_amount')
            ->groupBy('payrolls.currency', 'payroll_items.code', 'payroll_items.name', 'payroll_items.type')
            ->orderBy('payroll_items.type')
            ->orderBy('payroll_items.name')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'payroll_count' => (int) $row->payroll_count,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->buildQuery($request)
            ->with(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster'])
            ->latest('payrolls.id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Period', 'Employee Number', 'Employee Name', 'Department', 'Position', 'Status', 'Currency',
                'Basic Salary', 'Total Earnings', 'Total Deductions', 'Net Salary', 'Attendance Days',
                'Absent Days', 'Late Minutes', 'Unpaid Leave Days', 'Overtime Minutes', 'Finalized At', 'Paid At',
            ]);

            foreach ($rows as $payroll) {
                fputcsv($handle, [
                    $payroll->period?->name,
                    $payroll->employee?->employee_number,
                    $payroll->employee?->user?->name,
                    $payroll->employee?->departmentMaster?->name ?? $payroll->employee?->department,
                    $payroll->employee?->positionMaster?->name ?? $payroll->employee?->position,
                    $payroll->status,
                    $payroll->currency,
                    $payroll->basic_salary,
                    $payroll->total_earnings,
                    $payroll->total_deductions,
                    $payroll->net_salary,
                    $payroll->attendance_days,
                    $payroll->absent_days,
                    $payroll->late_minutes,
                    $payroll->unpaid_leave_days,
                    $payroll->overtime_minutes,
                    $payroll->finalized_at?->toDateTimeString(),
                    $payroll->paid_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'payroll-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function payslip(Payroll $payroll): Response
    {
        abort_unless(in_array($payroll->status, [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID], true), 409, 'Payslip is only available for finalized or paid payroll.');
        $payroll->load(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items']);
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

    private function buildQuery(Request $request): Builder
    {
        $request->validate([
            'payroll_period_id' => ['nullable', 'integer', 'exists:payroll_periods,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', 'in:draft,reviewed,finalized,paid,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Payroll::query();

        if ($request->filled('payroll_period_id')) {
            $query->where('payroll_period_id', $request->integer('payroll_period_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('currency')) {
            $query->where('currency', strtoupper((string) $request->input('currency')));
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($builder) => $builder->where('department_id', $request->integer('department_id')));
        }
        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')));
        }
        if ($request->filled('date_from')) {
            $query->whereHas('period', fn ($builder) => $builder->whereDate('start_date', '>=', $request->date('date_from')));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('period', fn ($builder) => $builder->whereDate('end_date', '<=', $request->date('date_to')));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query;
    }

    private function summary(Builder $query): array
    {
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $byCurrency = (clone $query)
            ->selectRaw('currency, COUNT(*) as payroll_count, SUM(total_earnings) as total_earnings, SUM(total_deductions) as total_deductions, SUM(net_salary) as net_salary')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'payroll_count' => (int) $row->payroll_count,
                'total_earnings' => number_format((float) $row->total_earnings, 2, '.', ''),
                'total_deductions' => number_format((float) $row->total_deductions, 2, '.', ''),
                'net_salary' => number_format((float) $row->net_salary, 2, '.', ''),
            ])
            ->values();

        return [
            'total_records' => (clone $query)->count(),
            'status_counts' => [
                'draft' => (int) ($statusCounts[Payroll::STATUS_DRAFT] ?? 0),
                'reviewed' => (int) ($statusCounts[Payroll::STATUS_REVIEWED] ?? 0),
                'finalized' => (int) ($statusCounts[Payroll::STATUS_FINALIZED] ?? 0),
                'paid' => (int) ($statusCounts[Payroll::STATUS_PAID] ?? 0),
                'cancelled' => (int) ($statusCounts[Payroll::STATUS_CANCELLED] ?? 0),
            ],
            'by_currency' => $byCurrency,
        ];
    }

    private function resourceCollection(iterable $resources): AnonymousResourceCollection
    {
        return PayrollResource::collection($resources);
    }
}
