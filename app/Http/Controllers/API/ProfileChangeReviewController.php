<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeProfile\ApproveProfileChangeRequest;
use App\Http\Requests\EmployeeProfile\IndexProfileChangeRequest;
use App\Http\Requests\EmployeeProfile\RejectProfileChangeRequest;
use App\Models\EmployeeProfileChangeRequest;
use App\Services\ProfileChangeRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileChangeReviewController extends Controller
{
    public function __construct(private readonly ProfileChangeRequestService $changeRequestService) {}

    public function index(IndexProfileChangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        $query = EmployeeProfileChangeRequest::query()
            ->with(['employee.user', 'requester', 'reviewer']);

        $this->applyFilters($query, $validated);
        $paginator = $query
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (EmployeeProfileChangeRequest $changeRequest) => $this->changeRequestService->transform($changeRequest, $actor));

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil karyawan berhasil diambil.',
            'data' => $paginator,
        ]);
    }

    public function show(Request $request, EmployeeProfileChangeRequest $profileChangeRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail permintaan perubahan profil berhasil diambil.',
            'data' => $this->changeRequestService->transform($profileChangeRequest, $request->user()),
        ]);
    }

    public function approve(
        ApproveProfileChangeRequest $request,
        EmployeeProfileChangeRequest $profileChangeRequest,
    ): JsonResponse {
        $profileChangeRequest = $this->changeRequestService->approve(
            $profileChangeRequest,
            $request->user(),
            $request->validated('review_note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil berhasil disetujui.',
            'data' => $this->changeRequestService->transform($profileChangeRequest, $request->user()),
        ]);
    }

    public function reject(
        RejectProfileChangeRequest $request,
        EmployeeProfileChangeRequest $profileChangeRequest,
    ): JsonResponse {
        $profileChangeRequest = $this->changeRequestService->reject(
            $profileChangeRequest,
            $request->user(),
            $request->validated('review_note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan profil berhasil ditolak.',
            'data' => $this->changeRequestService->transform($profileChangeRequest, $request->user()),
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn (Builder $builder, int $employeeId) => $builder->where('employee_id', $employeeId))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('created_at', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $builder, string $search): void {
                $builder->whereHas('employee', function (Builder $employeeQuery) use ($search): void {
                    $employeeQuery
                        ->where('employee_number', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            });

        ($filters['sort'] ?? 'newest') === 'oldest'
            ? $query->oldest()
            : $query->latest();
    }
}
