<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeProfile\IndexProfileChangeRequest;
use App\Http\Requests\EmployeeProfile\StoreProfileChangeRequest;
use App\Models\EmployeeProfileChangeRequest;
use App\Services\ProfileChangeRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileChangeRequestController extends Controller
{
    public function __construct(private readonly ProfileChangeRequestService $changeRequestService) {}

    public function index(IndexProfileChangeRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        $validated = $request->validated();
        $query = EmployeeProfileChangeRequest::query()
            ->where('employee_id', $employee->id)
            ->with(['employee.user', 'requester', 'reviewer']);

        $this->applyFilters($query, $validated);
        $paginator = $query
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (EmployeeProfileChangeRequest $changeRequest) => $this->changeRequestService->transform($changeRequest));

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil berhasil diambil.',
            'data' => $paginator,
        ]);
    }

    public function store(StoreProfileChangeRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        $changeRequest = $this->changeRequestService->create(
            $employee,
            $request->user(),
            $request->validatedChanges(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil berhasil diajukan.',
            'data' => $this->changeRequestService->transform($changeRequest),
        ], 201);
    }

    public function show(Request $request, EmployeeProfileChangeRequest $profileChangeRequest): JsonResponse
    {
        if (! $this->belongsToCurrentEmployee($request, $profileChangeRequest)) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail permintaan perubahan profil berhasil diambil.',
            'data' => $this->changeRequestService->transform($profileChangeRequest),
        ]);
    }

    public function destroy(Request $request, EmployeeProfileChangeRequest $profileChangeRequest): JsonResponse
    {
        if (! $this->belongsToCurrentEmployee($request, $profileChangeRequest)) {
            return $this->notFound();
        }

        $profileChangeRequest = $this->changeRequestService->cancel(
            $profileChangeRequest,
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil berhasil dibatalkan.',
            'data' => $this->changeRequestService->transform($profileChangeRequest),
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('created_at', '<=', $date));

        ($filters['sort'] ?? 'newest') === 'oldest'
            ? $query->oldest()
            : $query->latest();
    }

    private function belongsToCurrentEmployee(Request $request, EmployeeProfileChangeRequest $changeRequest): bool
    {
        $employee = $request->user()->employee;

        return $employee && (int) $changeRequest->employee_id === (int) $employee->id;
    }

    private function employeeNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Profil karyawan tidak ditemukan.',
        ], 404);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Permintaan perubahan profil tidak ditemukan.',
        ], 404);
    }
}
