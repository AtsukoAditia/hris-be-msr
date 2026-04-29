<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
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

        $attendances = $query->paginate($request->get('per_page', 15));
        $attendances->getCollection()->transform(fn ($attendance) => $this->transformAttendance($attendance));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat absensi berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
                'data'    => null,
            ], 404);
        }

        $attendance = Attendance::with(['shift'])
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        return response()->json([
            'success' => true,
            'message' => $attendance ? 'Absensi hari ini ditemukan.' : 'Belum ada absensi hari ini.',
            'data'    => $attendance ? $this->transformAttendance($attendance) : null,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $attendance = Attendance::with(['employee.user', 'shift'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail absensi berhasil diambil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $validated = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $existingAttendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if ($existingAttendance && $existingAttendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan check-in hari ini.',
                'data'    => $this->transformAttendance($existingAttendance),
            ], 422);
        }

        $attendance                     = $existingAttendance ?: new Attendance();
        $attendance->employee_id        = $employee->id;
        $attendance->shift_id           = $employee->shift?->id;
        $attendance->attendance_date    = today();
        $attendance->check_in_time      = now();
        $attendance->check_in_latitude  = $validated['latitude'] ?? null;
        $attendance->check_in_longitude = $validated['longitude'] ?? null;
        $attendance->status             = 'present';
        $attendance->note               = $validated['notes'] ?? $attendance->note;
        $attendance->save();

        $attendance->load(['shift']);

        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $validated = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus check-in terlebih dahulu sebelum check-out.',
            ], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan check-out hari ini.',
                'data'    => $this->transformAttendance($attendance),
            ], 422);
        }

        $attendance->check_out_time      = now();
        $attendance->check_out_latitude  = $validated['latitude'] ?? null;
        $attendance->check_out_longitude = $validated['longitude'] ?? null;
        $attendance->note                = $validated['notes'] ?? $attendance->note;
        $attendance->save();

        $attendance->load(['shift']);

        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    public function checkInQr(Request $request): JsonResponse
    {
        return $this->checkIn($request);
    }

    public function checkOutQr(Request $request): JsonResponse
    {
        return $this->checkOut($request);
    }

    public function getByEmployee(string $employeeId, Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'shift'])
            ->where('employee_id', $employeeId)
            ->latest('attendance_date');

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
            'message' => 'Data absensi karyawan berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur export absensi belum tersedia.',
        ], 501);
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = request()->user();

        if (!$user) {
            return null;
        }

        return Employee::with(['user', 'shift'])->where('user_id', $user->id)->first();
    }

    private function transformAttendance(Attendance $attendance): array
    {
        return [
            'id'               => $attendance->id,
            'employee_id'      => $attendance->employee_id,
            'shift_id'         => $attendance->shift_id,
            'attendance_date'  => optional($attendance->attendance_date)->format('Y-m-d'),
            'date'             => optional($attendance->attendance_date)->format('Y-m-d'),
            'check_in'         => optional($attendance->check_in_time)?->toDateTimeString(),
            'check_out'        => optional($attendance->check_out_time)?->toDateTimeString(),
            'check_in_time'    => optional($attendance->check_in_time)?->format('H:i:s'),
            'check_out_time'   => optional($attendance->check_out_time)?->format('H:i:s'),
            'check_in_lat'     => $attendance->check_in_latitude,
            'check_in_lng'     => $attendance->check_in_longitude,
            'check_out_lat'    => $attendance->check_out_latitude,
            'check_out_lng'    => $attendance->check_out_longitude,
            'status'           => $attendance->status,
            'notes'            => $attendance->note,
            'overtime_hours'   => $attendance->overtime_minutes,
            'employee'         => $attendance->relationLoaded('employee') && $attendance->employee ? [
                'id'         => $attendance->employee->id,
                'name'       => $attendance->employee->user->name ?? null,
                'department' => $attendance->employee->department,
                'position'   => $attendance->employee->position,
            ] : null,
            'shift'            => $attendance->relationLoaded('shift') && $attendance->shift ? [
                'id'   => $attendance->shift->id,
                'name' => $attendance->shift->name ?? null,
            ] : null,
        ];
    }
}
