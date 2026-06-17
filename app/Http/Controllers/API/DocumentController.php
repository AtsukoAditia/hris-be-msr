<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeDocument\ReplaceEmployeeDocumentRequest;
use App\Http\Requests\EmployeeDocument\StoreEmployeeDocumentRequest;
use App\Http\Requests\EmployeeDocument\UpdateEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentQueryService;
use App\Services\EmployeeDocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly EmployeeDocumentQueryService $queryService,
        private readonly EmployeeDocumentStorageService $storageService,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = collect(EmployeeDocument::CATEGORY_LABELS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Kategori dokumen berhasil diambil.',
            'data' => $categories,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->validateFilters($request, true);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen karyawan berhasil diambil.',
            'data' => $this->queryService->paginate($request),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $employee = isset($validated['employee_id'])
            ? Employee::findOrFail($validated['employee_id'])
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dokumen berhasil diambil.',
            'data' => $this->queryService->summary(
                $employee,
                (int) ($validated['expires_within_days'] ?? 30),
            ),
        ]);
    }

    public function employeeIndex(Request $request, Employee $employee): JsonResponse
    {
        $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen karyawan berhasil diambil.',
            'data' => $this->queryService->paginate($request, $employee),
        ]);
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $document = $this->storageService->create(
            $employee,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Dokumen karyawan berhasil diunggah.',
            'data' => $this->queryService->transform($document),
        ], 201);
    }

    public function show(Employee $employee, EmployeeDocument $employeeDocument): JsonResponse
    {
        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail dokumen berhasil diambil.',
            'data' => $this->queryService->transform($employeeDocument),
        ]);
    }

    public function update(
        UpdateEmployeeDocumentRequest $request,
        Employee $employee,
        EmployeeDocument $employeeDocument,
    ): JsonResponse {
        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        $document = $this->storageService->updateMetadata(
            $employeeDocument,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Metadata dokumen berhasil diperbarui.',
            'data' => $this->queryService->transform($document),
        ]);
    }

    public function replace(
        ReplaceEmployeeDocumentRequest $request,
        Employee $employee,
        EmployeeDocument $employeeDocument,
    ): JsonResponse {
        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        $document = $this->storageService->replace(
            $employeeDocument,
            $request->user(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'File dokumen berhasil diganti.',
            'data' => $this->queryService->transform($document),
        ]);
    }

    public function destroy(Employee $employee, EmployeeDocument $employeeDocument): JsonResponse
    {
        if (! $this->belongsTo($employee, $employeeDocument)) {
            return $this->notFound();
        }

        $this->storageService->delete($employeeDocument);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen karyawan berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function download(
        Employee $employee,
        EmployeeDocument $employeeDocument,
    ): JsonResponse|StreamedResponse {
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

    private function validateFilters(Request $request, bool $allowEmployee = false): void
    {
        $rules = [
            'category' => ['nullable', Rule::in(array_keys(EmployeeDocument::CATEGORY_LABELS))],
            'status' => ['nullable', Rule::in(['valid', 'expiring', 'expired', 'without_expiry'])],
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'expiry_asc', 'expiry_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        if ($allowEmployee) {
            $rules['employee_id'] = ['nullable', 'integer', 'exists:employees,id'];
        }

        $request->validate($rules);
    }

    private function belongsTo(Employee $employee, EmployeeDocument $document): bool
    {
        return (int) $document->employee_id === (int) $employee->id;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Dokumen karyawan tidak ditemukan.',
        ], 404);
    }
}
