<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollPeriodRequest;
use App\Http\Requests\Payroll\UpdatePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\ActivityLog;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PayrollPeriodAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $query = PayrollPeriod::query()->withCount('payrolls');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return PayrollPeriodResource::collection($query->latest('start_date')->paginate($perPage));
    }

    public function store(StorePayrollPeriodRequest $request): PayrollPeriodResource
    {
        $data = $request->validated();
        $this->assertNoOverlap($data['start_date'], $data['end_date']);
        $period = PayrollPeriod::create([...$data, 'status' => $data['status'] ?? PayrollPeriod::STATUS_OPEN]);

        ActivityLog::log(ActivityAction::CREATE, PayrollPeriod::class, $period->id, $period->toArray());

        return new PayrollPeriodResource($period->loadCount('payrolls'));
    }

    public function show(PayrollPeriod $payrollPeriod): PayrollPeriodResource
    {
        return new PayrollPeriodResource($payrollPeriod->loadCount('payrolls'));
    }

    public function update(UpdatePayrollPeriodRequest $request, PayrollPeriod $payrollPeriod): PayrollPeriodResource
    {
        if ($payrollPeriod->payrolls()->whereIn('status', [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID])->exists()) {
            abort(409, 'Period with finalized payroll cannot be changed.');
        }

        $data = $request->validated();
        $this->assertNoOverlap($data['start_date'], $data['end_date'], $payrollPeriod->id);
        $before = $payrollPeriod->toArray();
        $payrollPeriod->update($data);

        ActivityLog::log(ActivityAction::UPDATE, PayrollPeriod::class, $payrollPeriod->id, [
            'old' => $before,
            'new' => $payrollPeriod->fresh()->toArray(),
        ]);

        return new PayrollPeriodResource($payrollPeriod->fresh()->loadCount('payrolls'));
    }

    public function destroy(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if ($payrollPeriod->payrolls()->exists()) {
            return response()->json(['message' => 'Period with payroll records cannot be deleted.'], 409);
        }

        $payrollPeriod->delete();
        ActivityLog::log(ActivityAction::DELETE, PayrollPeriod::class, $payrollPeriod->id, ['name' => $payrollPeriod->name]);

        return response()->json(['message' => 'Payroll period deleted.']);
    }

    private function assertNoOverlap(string $startDate, string $endDate, ?int $exceptId = null): void
    {
        $overlap = PayrollPeriod::query()
            ->when($exceptId, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => ['Payroll period overlaps an existing period.'],
            ]);
        }
    }
}
