<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyContact\StoreEmergencyContactRequest;
use App\Http\Requests\EmergencyContact\UpdateEmergencyContactRequest;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Services\EmergencyContactService;
use App\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private readonly EmergencyContactService $contactService,
        private readonly EmployeeProfileService $profileService,
    ) {}

    public function index(Employee $employee): JsonResponse
    {
        return $this->listResponse($employee, 'Kontak darurat karyawan berhasil diambil.');
    }

    public function store(StoreEmergencyContactRequest $request, Employee $employee): JsonResponse
    {
        $contact = $this->contactService->create($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat berhasil ditambahkan.',
            'data' => $this->profileService->transformContact($contact),
        ], 201);
    }

    public function update(
        UpdateEmergencyContactRequest $request,
        Employee $employee,
        EmergencyContact $emergencyContact,
    ): JsonResponse {
        if (! $this->belongsTo($employee, $emergencyContact)) {
            return $this->notFound();
        }

        $contact = $this->contactService->update($employee, $emergencyContact, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat berhasil diperbarui.',
            'data' => $this->profileService->transformContact($contact),
        ]);
    }

    public function destroy(Employee $employee, EmergencyContact $emergencyContact): JsonResponse
    {
        if (! $this->belongsTo($employee, $emergencyContact)) {
            return $this->notFound();
        }

        $this->contactService->delete($employee, $emergencyContact);

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        return $this->listResponse($employee, 'Kontak darurat saya berhasil diambil.');
    }

    public function myStore(StoreEmergencyContactRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        $contact = $this->contactService->create($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat saya berhasil ditambahkan.',
            'data' => $this->profileService->transformContact($contact),
        ], 201);
    }

    public function myUpdate(
        UpdateEmergencyContactRequest $request,
        EmergencyContact $emergencyContact,
    ): JsonResponse {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        if (! $this->belongsTo($employee, $emergencyContact)) {
            return $this->notFound();
        }

        $contact = $this->contactService->update($employee, $emergencyContact, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat saya berhasil diperbarui.',
            'data' => $this->profileService->transformContact($contact),
        ]);
    }

    public function myDestroy(Request $request, EmergencyContact $emergencyContact): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->employeeNotFound();
        }

        if (! $this->belongsTo($employee, $emergencyContact)) {
            return $this->notFound();
        }

        $this->contactService->delete($employee, $emergencyContact);

        return response()->json([
            'success' => true,
            'message' => 'Kontak darurat saya berhasil dihapus.',
            'data' => null,
        ]);
    }

    private function listResponse(Employee $employee, string $message): JsonResponse
    {
        $contacts = $employee->emergencyContacts()
            ->get()
            ->map(fn (EmergencyContact $contact) => $this->profileService->transformContact($contact))
            ->values();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $contacts,
        ]);
    }

    private function belongsTo(Employee $employee, EmergencyContact $contact): bool
    {
        return (int) $contact->employee_id === (int) $employee->id;
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
            'message' => 'Kontak darurat tidak ditemukan.',
        ], 404);
    }
}
