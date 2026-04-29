<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Admin/HR: Semua data absensi dengan filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'shift'])->latest('attendance_date');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        }

        $attendances = $query->paginate($request->get('per_page', 15));
        $attendances->getCollection()->transform(fn ($attendance) => $this->transformAttendance($attendance));

        return response()->json([
            'success' => true,
            'message' => 'Data absensi berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

    /**
     * Employee: Riwayat absensi milik sendiri
     */
    public function my(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
                'data'    => [],
            ], 404);
        }

        $query = Attendance::with(['shift'])
            ->where('employee_id', $employee->id)
            ->latest('attendance_date');

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->paginate($request->get('per_page', 15));
        $attendances->getCollection()->transform(fn ($attendance) => $this->transformAttendance($attendance));

        return response()->json([
            'success' => true,
            'message' => 'Data absensi karyawan berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

    /**
     * Absensi hari ini milik user yang login
     */
    public function today(): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        $attendance = Attendance::with(['shift'])
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi hari ini.',
            'data'    => $attendance ? $this->transformAttendance($attendance) : null,
        ]);
    }

    /**
     * Check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if ($existing && $existing->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-in hari ini.'], 422);
        }

        $validated = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($existing) {
            $attendance = $existing;
        } else {
            $attendance = new Attendance();
            $attendance->employee_id     = $employee->id;
            $attendance->attendance_date = today();
            $attendance->status          = 'present';
        }

        $attendance->check_in_time      = now();
        $attendance->check_in_latitude  = $validated['latitude'] ?? null;
        $attendance->check_in_longitude = $validated['longitude'] ?? null;
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    /**
     * Check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda belum melakukan check-in hari ini.'], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-out hari ini.'], 422);
        }

        $validated = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $attendance->check_out_time      = now();
        $attendance->check_out_latitude  = $validated['latitude'] ?? null;
        $attendance->check_out_longitude = $validated['longitude'] ?? null;
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    /**
     * Check-in via QR code
     */
    public function checkInQr(Request $request): JsonResponse
    {
        return $this->checkIn($request);
    }

    /**
     * Check-out via QR code
     */
    public function checkOutQr(Request $request): JsonResponse
    {
        return $this->checkOut($request);
    }

    /**
     * Data absensi satu karyawan tertentu (admin/hr)
     */
    public function getByEmployee(int $employeeId, Request $request): JsonResponse
    {
        $query = Attendance::with(['shift'])
            ->where('employee_id', $employeeId)
            ->latest('attendance_date');

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        }

        $attendances = $query->paginate($request->get('per_page', 15));
        $attendances->getCollection()->transform(fn ($a) => $this->transformAttendance($a));

        return response()->json([
            'success' => true,
            'message' => 'Data absensi karyawan berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

    /**
     * Export absensi (placeholder)
     */
    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur export absensi belum tersedia.',
        ], 501);
    }

    /**
     * Ambil employee berdasarkan user yang sedang login
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = request()->user();

        if (!$user) {
            return null;
        }

        return Employee::with(['user', 'shift'])->where('user_id', $user->id)->first();
    }

    /**
     * Transform attendance model ke array response
     */
    private function transformAttendance(Attendance $attendance): array
    {
        return [
            'id'               => $attendance->id,
            'employee_id'      => $attendance->employee_id,
            'shift_id'         => $attendance->shift_id,
            'attendance_date'  => optional($attendance->attendance_date)->format('Y-m-d'),
            'check_in_time'    => optional($attendance->check_in_time)->format('H:i:s'),
            'check_out_time'   => optional($attendance->check_out_time)->format('H:i:s'),
            'check_in_lat'     => $attendance->check_in_latitude,
            'check_in_lng'     => $attendance->check_in_longitude,
            'check_out_lat'    => $attendance->check_out_latitude,
            'check_out_lng'    => $attendance->check_out_longitude,
            'status'           => $attendance->status,
            'notes'            => $attendance->notes,
            'late_minutes'     => $attendance->late_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'employee'         => $attendance->relationLoaded('employee') && $attendance->employee ? [
                'id'         => $attendance->employee->id,
                'name'       => $attendance->employee->user->name ?? null,
                'department' => $attendance->employee->department ?? null,
                'position'   => $attendance->employee->position ?? null,
            ] : null,
            'shift' => $attendance->relationLoaded('shift') && $attendance->shift ? [
                'id'   => $attendance->shift->id,
                'name' => $attendance->shift->name ?? null,
            ] : null,
        ];
    }
}
