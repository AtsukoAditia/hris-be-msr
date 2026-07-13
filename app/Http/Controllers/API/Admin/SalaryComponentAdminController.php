<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryComponentRequest;
use App\Http\Requests\Payroll\UpdateSalaryComponentRequest;
use App\Http\Resources\SalaryComponentResource;
use App\Models\ActivityLog;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalaryComponentAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $query = SalaryComponent::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        } elseif ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        return SalaryComponentResource::collection($query->orderBy('type')->orderBy('name')->paginate($perPage));
    }

    public function store(StoreSalaryComponentRequest $request): SalaryComponentResource
    {
        $data = $this->normalizeCalculationFields($request->validated());
        $component = SalaryComponent::create([
            ...$data,
            'default_amount' => $data['default_amount'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        ActivityLog::log(ActivityAction::CREATE, SalaryComponent::class, $component->id, $component->toArray());

        return new SalaryComponentResource($component);
    }

    public function show(SalaryComponent $salaryComponent): SalaryComponentResource
    {
        return new SalaryComponentResource($salaryComponent);
    }

    public function update(UpdateSalaryComponentRequest $request, SalaryComponent $salaryComponent): SalaryComponentResource
    {
        $before = $salaryComponent->toArray();
        $salaryComponent->update($this->normalizeCalculationFields($request->validated()));

        ActivityLog::log(ActivityAction::UPDATE, SalaryComponent::class, $salaryComponent->id, [
            'old' => $before,
            'new' => $salaryComponent->fresh()->toArray(),
        ]);

        return new SalaryComponentResource($salaryComponent->fresh());
    }

    public function destroy(SalaryComponent $salaryComponent): JsonResponse
    {
        $salaryComponent->delete();
        ActivityLog::log(ActivityAction::DELETE, SalaryComponent::class, $salaryComponent->id, [
            'code' => $salaryComponent->code,
            'name' => $salaryComponent->name,
        ]);

        return response()->json(['message' => 'Salary component deleted.']);
    }

    private function normalizeCalculationFields(array $data): array
    {
        if (($data['calculation_type'] ?? null) !== SalaryComponent::CALCULATION_PERCENTAGE) {
            $data['percentage'] = null;
        }

        if (($data['calculation_type'] ?? null) !== SalaryComponent::CALCULATION_FORMULA) {
            $data['formula'] = null;
        }

        return $data;
    }
}
