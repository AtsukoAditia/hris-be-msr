<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeProfile\UpdateEmployeeProfileRequest;
use App\Http\Requests\EmployeeProfile\UpdateMyProfileRequest;
use App\Models\Employee;
use App\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly EmployeeProfileService $profileService) {}

    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profil karyawan berhasil diambil.',
            'data' => $this->profileService->transform($employee),
        ]);
    }

    public function update(UpdateEmployeeProfileRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->profileService->update($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil karyawan berhasil diperbarui.',
            'data' => $this->profileService->transform($employee),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil saya berhasil diambil.',
            'data' => $this->profileService->transform($employee),
        ]);
    }

    public function updateMe(UpdateMyProfileRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->notFound();
        }

        $employee = $this->profileService->update($employee, $request->directUpdates());

        return response()->json([
            'success' => true,
            'message' => 'Profil saya berhasil diperbarui.',
            'data' => $this->profileService->transform($employee),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Profil karyawan tidak ditemukan.',
        ], 404);
    }
}
