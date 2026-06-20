<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreEmployeeSalaryProfileRequest;
use App\Http\Requests\Payroll\UpdateEmployeeSalaryProfileRequest;
use App\Http\Resources\EmployeeSalaryProfileResource;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeSalaryProfile;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryProfileAdminController extends Controller
{
    public function index(Employee $employee): AnonymousResourceCollection
    {
        $profiles = $employee->salaryProfiles()->with('components.salaryComponent')->latest('effective_from')->get();

        return EmployeeSalaryProfileResource::collection($profiles);
    }

    public function store(StoreEmployeeSalaryProfileRequest $request, Employee $employee): EmployeeSalaryProfileResource
    {
        $profile = DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();
            $components = $data['components'] ?? [];
            unset($data['components']);

            $this->assertNoOverlap($employee->id, $data['effective_from'], $data['effective_to'] ?? null);

            $profile = $employee->salaryProfiles()->create([...$data, 'is_active' => $data['is_active'] ?? true]);
            $this->syncComponents($profile, $components);
            $this->syncCurrentBasicSalary($employee, $profile);

            ActivityLog::log(ActivityAction::CREATE, EmployeeSalaryProfile::class, $profile->id, [
                'employee_id' => $employee->id,
                'effective_from' => $profile->effective_from?->toDateString(),
                'basic_salary' => $profile->basic_salary,
            ]);

            return $profile;
        });

        return new EmployeeSalaryProfileResource($profile->load($this->relations()));
    }

    public function show(EmployeeSalaryProfile $salaryProfile): EmployeeSalaryProfileResource
    {
        return new EmployeeSalaryProfileResource($salaryProfile->load($this->relations()));
    }

    public function update(UpdateEmployeeSalaryProfileRequest $request, EmployeeSalaryProfile $salaryProfile): EmployeeSalaryProfileResource
    {
        if ($salaryProfile->payrolls()->whereIn('status', [Payroll::STATUS_FINALIZED, Payroll::STATUS_PAID])->exists()) {
            abort(409, 'Salary profile is referenced by finalized payroll and cannot be changed.');
        }

        DB::transaction(function () use ($request, $salaryProfile) {
            $data = $request->validated();
            $components = $data['components'] ?? [];
            unset($data['components']);

            $this->assertNoOverlap($salaryProfile->employee_id, $data['effective_from'], $data['effective_to'] ?? null, $salaryProfile->id);

            $before = $salaryProfile->toArray();
            $salaryProfile->update($data);
            $this->syncComponents($salaryProfile, $components);
            $this->syncCurrentBasicSalary($salaryProfile->employee, $salaryProfile->fresh());

            ActivityLog::log(ActivityAction::UPDATE, EmployeeSalaryProfile::class, $salaryProfile->id, [
                'old' => $before,
                'new' => $salaryProfile->fresh()->toArray(),
            ]);
        });

        return new EmployeeSalaryProfileResource($salaryProfile->fresh()->load($this->relations()));
    }

    public function destroy(EmployeeSalaryProfile $salaryProfile): JsonResponse
    {
        if ($salaryProfile->payrolls()->exists()) {
            return response()->json(['message' => 'Salary profile is already referenced by payroll and cannot be deleted.'], 409);
        }

        $salaryProfile->delete();
        ActivityLog::log(ActivityAction::DELETE, EmployeeSalaryProfile::class, $salaryProfile->id, [
            'employee_id' => $salaryProfile->employee_id,
        ]);

        return response()->json(['message' => 'Salary profile deleted.']);
    }

    private function assertNoOverlap(int $employeeId, string $effectiveFrom, ?string $effectiveTo, ?int $exceptId = null): void
    {
        $overlap = EmployeeSalaryProfile::query()
            ->where('employee_id', $employeeId)
            ->when($exceptId, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->whereDate('effective_from', '<=', $effectiveTo ?? '9999-12-31')
            ->where(function (Builder $query) use ($effectiveFrom) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => ['Salary profile effective dates overlap an existing profile.'],
            ]);
        }
    }

    private function syncComponents(EmployeeSalaryProfile $profile, array $components): void
    {
        $profile->components()->delete();

        foreach ($components as $component) {
            $profile->components()->create([
                'salary_component_id' => $component['salary_component_id'],
                'amount' => $component['amount'] ?? null,
                'percentage' => $component['percentage'] ?? null,
                'formula' => $component['formula'] ?? null,
            ]);
        }
    }

    private function syncCurrentBasicSalary(Employee $employee, EmployeeSalaryProfile $profile): void
    {
        if (! $profile->is_active || $profile->effective_from->isFuture()) {
            return;
        }

        if ($profile->effective_to && $profile->effective_to->isPast()) {
            return;
        }

        $employee->update(['basic_salary' => $profile->basic_salary]);
    }

    private function relations(): array
    {
        return ['employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'components.salaryComponent'];
    }
}
