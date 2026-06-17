<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentQueryService;
use App\Services\EmployeeDocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyDocumentController extends Controller
{
    public function __construct(
        private readonly EmployeeDocumentQueryService $queryService,
        private readonly EmployeeDocumentStorageService $storageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        if (! $employee) {
            return $this->employeeNotFound();
        }

        $request->validate([
            'category' => ['nullable', Rule::in(array_keys(EmployeeDocument::CATEGORY_LABELS))],
            'status' => ['nullable', Rule::in(['valid', 'expiring', 'expired', 'without_expiry'])],
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'expiry_asc', 'expiry_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen saya berhasil diambil.',
            'data' => $this->queryService->paginate($request, $employee),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        if (! $employee) {
            return $this->employeeNotFound();
        }

        $validated = $request->validate([
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dokumen saya berhasil diambil.',
            'data' => $this->queryService->summary(
                $employee,
                (int) ($validated['expires_within_days'] ?? 30),
            ),
        ]);
    }

    public function show(Request $request, EmployeeDocument $employeeDocument): JsonResponse
    {
        $employee = $this->employee($request);

        if (! $employee) {
            return $this->employeeNotFound();
        }

        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail dokumen saya berhasil diambil.',
            'data' => $this->queryService->transform($employeeDocument),
        ]);
    }

    public function download(
        Request $request,
        EmployeeDocument $employeeDocument,
    ): JsonResponse|StreamedResponse {
        $employee = $this->employee($request);

        if (! $employee) {
            return $this->employeeNotFound();
        }

        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        try {
            return $this->storageService->download($employeeDocument);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'File dokumen tidak ditemukan di penyimpanan.',
            ], 404);
        }
    }

    private function employee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    private function belongsTo(Employee $employee, EmployeeDocument $document): bool
    {
        return (int) $document->employee_id === (int) $employee->id;
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
            'message' => 'Dokumen karyawan tidak ditemukan.',
        ], 404);
    }
}
