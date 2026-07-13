<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\CancelPayrollRequest;
use App\Http\Requests\Payroll\GeneratePayrollRequest;
use App\Http\Resources\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayrollAdminController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $query = Payroll::query()->with($this->payrollService->relations());

        if ($request->filled('payroll_period_id')) {
            $query->where('payroll_period_id', $request->integer('payroll_period_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return PayrollResource::collection($query->latest()->paginate($perPage));
    }

    public function show(Payroll $payroll): PayrollResource
    {
        return new PayrollResource($payroll->load($this->payrollService->relations()));
    }

    public function generate(GeneratePayrollRequest $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        try {
            $payrolls = $this->payrollService->generatePeriod(
                $payrollPeriod,
                $request->validated('employee_ids') ?? [],
                $request->user(),
            );

            return response()->json([
                'message' => 'Draft payroll generated successfully.',
                'data' => PayrollResource::collection($payrolls)->resolve($request),
                'meta' => ['generated_count' => $payrolls->count()],
            ]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function recalculate(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->recalculate($payroll, $request->user()));
    }

    public function submit(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->submit($payroll, $request->user()));
    }

    public function approve(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->approve($payroll, $request->user()));
    }

    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'payroll_period_id' => 'required|integer',
        ]);

        $result = $this->payrollService->simulate(
            $validated['employee_id'],
            $validated['payroll_period_id'],
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }

    public function review(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->review($payroll, $request->user()));
    }

    public function finalize(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->finalize($payroll, $request->user()));
    }

    public function markPaid(Request $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->markPaid($payroll, $request->user()));
    }

    public function cancel(CancelPayrollRequest $request, Payroll $payroll): JsonResponse
    {
        return $this->runTransition(fn () => $this->payrollService->cancel(
            $payroll,
            $request->user(),
            $request->validated('reason'),
        ));
    }

    private function runTransition(callable $callback): JsonResponse
    {
        try {
            $payroll = $callback();

            return response()->json([
                'message' => 'Payroll status updated successfully.',
                'data' => (new PayrollResource($payroll))->resolve(request()),
            ]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }
}
