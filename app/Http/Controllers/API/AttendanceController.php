<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'shift'])->latest('attendance_date')->latest('created_at');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $attendances = $query->paginate($perPage);
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
            ->latest('attendance_date')
            ->latest('created_at');

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $attendances = $query->paginate($perPage);
        $attendances->getCollection()->transform(fn ($attendance) => $this->transformAttendance($attendance));

        return response()->json([
            'success' => true,
            'message' => 'Data absensi karyawan berhasil diambil.',
            'data'    => $attendances,
        ]);
    }

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

        $shiftSchedule = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', today())
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi hari ini.',
            'data'    => [
                'attendance' => $attendance ? $this->transformAttendance($attendance) : null,
                'shift_schedule' => $shiftSchedule,
                'shift' => $shiftSchedule?->shift,
            ],
        ]);
    }

    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['employee.user', 'shift']);

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
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'note' => 'nullable|string|max:500',
        ]);

        $shiftSchedule = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', today())
            ->first();

        $shift = $shiftSchedule?->shift;
        $now = now();
        $lateMinutes = $this->calculateLateMinutes($shift?->start_time, (int) ($shift?->late_tolerance ?? 15), $now);

        $attendance = $existing ?: new Attendance();
        $attendance->employee_id = $employee->id;
        $attendance->shift_id = $shift?->id;
        $attendance->attendance_date = today();
        $attendance->status = $lateMinutes > 0 ? 'late' : 'present';
        $attendance->late_minutes = $lateMinutes;
        $attendance->check_in_time = $now;
        $attendance->check_in_latitude = $validated['latitude'] ?? null;
        $attendance->check_in_longitude = $validated['longitude'] ?? null;
        $attendance->note = $validated['note'] ?? $attendance->note;

        if ($request->hasFile('photo')) {
            $attendance->check_in_photo = $request->file('photo')->store('attendance/check-in', 'public');
        }

        $attendance->save();
        $attendance->load(['employee.user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => $lateMinutes > 0 ? 'Check-in berhasil, status terlambat.' : 'Check-in berhasil.',
            'data'    => $this->transformAttendance($attendance),
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $attendance = Attendance::with('shift')
            ->where('employee_id', $employee->id)
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
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'note' => 'nullable|string|max:500',
        ]);

        $attendance->check_out_time = now();
        $attendance->check_out_latitude = $validated['latitude'] ?? null;
        $attendance->check_out_longitude = $validated['longitude'] ?? null;
        $attendance->note = $validated['note'] ?? $attendance->note;

        if ($request->hasFile('photo')) {
            $attendance->check_out_photo = $request->file('photo')->store('attendance/check-out', 'public');
        }

        $attendance->overtime_minutes = $this->calculateOvertimeMinutes($attendance);
        $attendance->save();
        $attendance->load(['employee.user', 'shift']);

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

    public function getByEmployee(int $employeeId, Request $request): JsonResponse
    {
        $query = Attendance::with(['shift'])
            ->where('employee_id', $employeeId)
            ->latest('attendance_date')
            ->latest('created_at');

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

        return Employee::with(['user'])->where('user_id', $user->id)->first();
    }

    private function calculateLateMinutes(?string $shiftStartTime, int $toleranceMinutes, Carbon $checkInTime): int
    {
        if (!$shiftStartTime) {
            return 0;
        }

        $scheduledStart = Carbon::parse(today()->format('Y-m-d') . ' ' . substr($shiftStartTime, 0, 5));
        $allowedTime = $scheduledStart->copy()->addMinutes($toleranceMinutes);

        return $checkInTime->greaterThan($allowedTime)
            ? $allowedTime->diffInMinutes($checkInTime)
            : 0;
    }

    private function calculateOvertimeMinutes(Attendance $attendance): int
    {
        if (!$attendance->shift || !$attendance->shift->end_time || !$attendance->check_out_time) {
            return 0;
        }

        $shiftEnd = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . substr($attendance->shift->end_time, 0, 5));

        if ($attendance->shift->is_overnight && $shiftEnd->lessThanOrEqualTo(Carbon::parse($attendance->check_in_time))) {
            $shiftEnd->addDay();
        }

        return $attendance->check_out_time->greaterThan($shiftEnd)
            ? $shiftEnd->diffInMinutes($attendance->check_out_time)
            : 0;
    }

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
            'check_in_photo'   => $attendance->check_in_photo,
            'check_out_photo'  => $attendance->check_out_photo,
            'check_in_photo_url' => $attendance->check_in_photo ? asset('storage/' . $attendance->check_in_photo) : null,
            'check_out_photo_url' => $attendance->check_out_photo ? asset('storage/' . $attendance->check_out_photo) : null,
            'status'           => $attendance->status,
            'note'             => $attendance->note,
            'notes'            => $attendance->note,
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
                'code' => $attendance->shift->code ?? null,
                'start_time' => $attendance->shift->start_time ?? null,
                'end_time' => $attendance->shift->end_time ?? null,
                'late_tolerance' => $attendance->shift->late_tolerance ?? null,
                'is_overnight' => $attendance->shift->is_overnight ?? false,
            ] : null,
        ];
    }
}
