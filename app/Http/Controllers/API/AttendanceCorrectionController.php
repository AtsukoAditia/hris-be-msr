<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrection\ApproveCorrectionRequest;
use App\Http\Requests\AttendanceCorrection\IndexAttendanceCorrectionRequest;
use App\Http\Requests\AttendanceCorrection\ManualCorrectionRequest;
use App\Http\Requests\AttendanceCorrection\RejectCorrectionRequest;
use App\Http\Requests\AttendanceCorrection\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private readonly AttendanceCorrectionService $correctionService
    ) {}

    /**
     * List correction requests (Admin/HR/Manager view).
     */
    public function index(IndexAttendanceCorrectionRequest $request): JsonResponse
    {
        $user = Auth::user();
        $filters = $request->validated();
        $filters['per_page'] = min(max((int) $request->get('per_page', 15), 1), 100);

        // Manager only sees their team's requests
        if ($user->isManager() && ! $user->isAdmin() && ! $user->isHr()) {
            $teamEmployeeIds = Employee::where('direct_manager_id', $user->employee?->id)
                ->pluck('id')
                ->toArray();
            if ($user->employee) {
                $teamEmployeeIds[] = $user->employee->id;
            }
            $filters['employee_ids'] = $teamEmployeeIds;
        }

        $result = $this->correctionService->list(null, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Data permintaan koreksi kehadiran berhasil diambil.',
            'data' => $result,
        ]);
    }

    /**
     * List correction requests for the authenticated employee only.
     */
    public function my(IndexAttendanceCorrectionRequest $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        $filters = $request->validated();
        $filters['per_page'] = min(max((int) $request->get('per_page', 15), 1), 100);

        $result = $this->correctionService->list($employee, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat permintaan koreksi berhasil diambil.',
            'data' => $result,
        ]);
    }

    /**
     * Employee submits a correction request.
     */
    public function store(StoreAttendanceCorrectionRequest $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        try {
            $correctionRequest = $this->correctionService->submit(
                $employee,
                $request->validated(),
                $request->file('attachment')
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan koreksi kehadiran berhasil dikirim.',
            'data' => $correctionRequest->load(['attendance', 'employee', 'reviewer']),
        ], 201);
    }

    /**
     * Show a single correction request.
     */
    public function show(AttendanceCorrectionRequest $correction): JsonResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && ! $user->isHr() && ! $user->isManager()) {
            if ($correction->employee_id !== $user->employee?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan.',
                ], 403);
            }
        }

        if ($user->isManager() && ! $user->isAdmin() && ! $user->isHr()) {
            if ($correction->employee_id !== $user->employee?->id) {
                $isTeamMember = Employee::where('id', $correction->employee_id)
                    ->where('direct_manager_id', $user->employee?->id)
                    ->exists();

                if (! $isTeamMember) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak diizinkan.',
                    ], 403);
                }
            }
        }

        $correction->load(['attendance', 'employee', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => 'Detail permintaan koreksi berhasil diambil.',
            'data' => $correction,
        ]);
    }

    /**
     * Approve a correction request.
     */
    public function approve(ApproveCorrectionRequest $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $reviewer = Auth::user();
        $this->authorizeReviewer($reviewer, $correction);

        try {
            $result = $this->correctionService->approve(
                $correction,
                $request->validated()['review_note'] ?? null
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan koreksi berhasil disetujui.',
            'data' => $result->load(['attendance', 'employee', 'reviewer']),
        ]);
    }

    /**
     * Reject a correction request.
     */
    public function reject(RejectCorrectionRequest $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $reviewer = Auth::user();
        $this->authorizeReviewer($reviewer, $correction);

        try {
            $result = $this->correctionService->reject(
                $correction,
                $request->validated()['review_note']
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan koreksi berhasil ditolak.',
            'data' => $result->load(['attendance', 'employee', 'reviewer']),
        ]);
    }

    /**
     * Cancel a pending correction request (employee only).
     */
    public function cancel(AttendanceCorrectionRequest $correction): JsonResponse
    {
        $user = Auth::user();

        if ($correction->employee_id !== $user->employee?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan.',
            ], 403);
        }

        try {
            $result = $this->correctionService->cancel($correction);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan koreksi berhasil dibatalkan.',
            'data' => $result->load(['attendance', 'employee', 'reviewer']),
        ]);
    }

    /**
     * Admin/HR manual correction (bypasses approval).
     */
    public function manualCorrection(ManualCorrectionRequest $request): JsonResponse
    {
        $employeeId = $request->validated()['employee_id'];
        $employee = Employee::findOrFail($employeeId);

        try {
            $correction = $this->correctionService->manualCorrection(
                $employee,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Koreksi manual berhasil dilakukan.',
            'data' => $correction->load(['attendance', 'employee', 'reviewer']),
        ], 201);
    }

    /**
     * Download attachment for a correction request.
     */
    public function downloadAttachment(AttendanceCorrectionRequest $correction): JsonResponse|BinaryFileResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && ! $user->isHr() && ! $user->isManager()) {
            if ($correction->employee_id !== $user->employee?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan.',
                ], 403);
            }
        }

        if ($user->isManager() && ! $user->isAdmin() && ! $user->isHr()) {
            if ($correction->employee_id !== $user->employee?->id) {
                $isTeamMember = Employee::where('id', $correction->employee_id)
                    ->where('direct_manager_id', $user->employee?->id)
                    ->exists();

                if (! $isTeamMember) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak diizinkan.',
                    ], 403);
                }
            }
        }

        if (empty($correction->attachment_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran tidak ditemukan.',
            ], 404);
        }

        if (! Storage::disk('local')->exists($correction->attachment_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File lampiran tidak ditemukan di storage.',
            ], 404);
        }

        return response()->download(
            Storage::disk('local')->path($correction->attachment_path),
            $correction->attachment_name ?? 'attachment'
        );
    }

    /**
     * Authorize reviewer scope.
     */
    private function authorizeReviewer($reviewer, AttendanceCorrectionRequest $correction): void
    {
        if ($reviewer->isAdmin() || $reviewer->isHr()) {
            return;
        }

        if ($reviewer->isManager()) {
            $isTeamMember = Employee::where('id', $correction->employee_id)
                ->where('direct_manager_id', $reviewer->employee?->id)
                ->exists();

            if ($isTeamMember) {
                return;
            }

            abort(403, 'Anda hanya dapat menyetujui/menolak koreksi dari anggota tim Anda.');
        }

        abort(403, 'Anda tidak memiliki izin untuk melakukan aksi ini.');
    }
}
