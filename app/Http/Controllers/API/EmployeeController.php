<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeMutationService;
use App\Services\EmployeeQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeQueryService $queryService,
        private readonly EmployeeMutationService $mutationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diambil.',
            'data' => $this->queryService->paginate($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->mutationService->create($request);
        $this->queryService->load($employee);

        return response()->json([
            'success' => true,
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => $this->queryService->transform($employee),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->queryService->load($employee);

        return response()->json([
            'success' => true,
            'message' => 'Detail karyawan berhasil diambil.',
            'data' => $this->queryService->transform($employee),
        ]);
    }

    public function profile(Employee $employee): JsonResponse
    {
        $this->queryService->load($employee);

        return response()->json([
            'success' => true,
            'message' => 'Profil karyawan berhasil diambil.',
            'data' => $this->queryService->transform($employee),
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $employee = $this->mutationService->update($request, $employee);
        $this->queryService->load($employee);

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $this->queryService->transform($employee),
        ]);
    }

    public function enrollFace(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'face_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($employee->face_image) {
            Storage::disk('public')->delete($employee->face_image);
        }

        $employee->update([
            'face_image' => $validated['face_image']->store('face-enrollments', 'public'),
            'face_registered_at' => now(),
        ]);

        $employee->refresh();
        $this->queryService->load($employee);

        return response()->json([
            'success' => true,
            'message' => 'Foto wajah absensi berhasil disimpan.',
            'data' => $this->queryService->transform($employee),
        ]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if ((int) $employee->user_id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akun yang sedang digunakan tidak dapat dihapus.',
            ], 422);
        }

        DB::transaction(function () use ($employee) {
            $user = $employee->user;

            if ($employee->face_image) {
                Storage::disk('public')->delete($employee->face_image);
            }

            $employee->delete();
            $user?->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil dihapus.',
            'data' => null,
        ]);
    }
}
