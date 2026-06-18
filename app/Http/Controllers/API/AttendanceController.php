<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceQrToken;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            'data' => $attendances,
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
                'data' => [],
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
            'data' => $attendances,
        ]);
    }

    public function today(): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
                'data' => null,
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
            'data' => [
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
            'data' => $this->transformAttendance($attendance),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        return $this->handleCheckIn($request, false);
    }

    public function checkOut(Request $request): JsonResponse
    {
        return $this->handleCheckOut($request, false);
    }

    public function checkInQr(Request $request): JsonResponse
    {
        return $this->handleCheckIn($request, true);
    }

    public function checkOutQr(Request $request): JsonResponse
    {
        return $this->handleCheckOut($request, true);
    }

    private function handleCheckIn(Request $request, bool $requiresQr): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if ($existing && $existing->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-in hari ini.'], 422);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12000',
            'note' => 'nullable|string|max:500',
            'qr_token' => $requiresQr ? 'required|string' : 'nullable|string',
        ]);

        $qrToken = null;
        if ($requiresQr) {
            $qrToken = $this->validateQrToken($validated['qr_token'], 'check_in');
            if ($qrToken instanceof JsonResponse) {
                return $qrToken;
            }
        }

        $radiusValidation = $this->validateRadius($request, 'check_in');
        if (! $radiusValidation['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $radiusValidation['message'],
                'data' => $radiusValidation,
            ], 422);
        }

        $shiftSchedule = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', today())
            ->first();

        $shift = $shiftSchedule?->shift;
        $now = now();
        $lateMinutes = $this->calculateLateMinutes($shift?->start_time, (int) ($shift?->late_tolerance ?? 15), $now);

        $attendance = $existing ?: new Attendance;
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
            'data' => array_merge($this->transformAttendance($attendance), [
                'radius_validation' => $radiusValidation,
                'qr' => $requiresQr ? $this->transformQrToken($qrToken) : null,
            ]),
        ]);
    }

    private function handleCheckOut(Request $request, bool $requiresQr): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $attendance = Attendance::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (! $attendance || ! $attendance->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda belum melakukan check-in hari ini.'], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-out hari ini.'], 422);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12000',
            'note' => 'nullable|string|max:500',
            'qr_token' => $requiresQr ? 'required|string' : 'nullable|string',
        ]);

        $qrToken = null;
        if ($requiresQr) {
            $qrToken = $this->validateQrToken($validated['qr_token'], 'check_out');
            if ($qrToken instanceof JsonResponse) {
                return $qrToken;
            }
        }

        $radiusValidation = $this->validateRadius($request, 'check_out');
        if (! $radiusValidation['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $radiusValidation['message'],
                'data' => $radiusValidation,
            ], 422);
        }

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
            'data' => array_merge($this->transformAttendance($attendance), [
                'radius_validation' => $radiusValidation,
                'qr' => $requiresQr ? $this->transformQrToken($qrToken) : null,
            ]),
        ]);
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
            'data' => $attendances,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur export absensi belum tersedia. Gunakan endpoint /reports/export untuk export laporan.',
        ], 501);
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = request()->user();

        if (! $user) {
            return null;
        }

        return Employee::with(['user'])->where('user_id', $user->id)->first();
    }

    private function validateRadius(Request $request, string $action): array
    {
        $setting = AttendanceSetting::current();

        if (! $setting->is_radius_enabled) {
            return [
                'allowed' => true,
                'enabled' => false,
                'message' => 'Validasi radius tidak aktif.',
                'distance_meters' => null,
                'radius_meters' => $setting->radius_meters,
            ];
        }

        if (! $setting->hasOfficeCoordinate()) {
            return [
                'allowed' => false,
                'enabled' => true,
                'message' => 'Koordinat kantor belum diatur.',
                'distance_meters' => null,
                'radius_meters' => $setting->radius_meters,
            ];
        }

        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            return [
                'allowed' => false,
                'enabled' => true,
                'message' => 'Lokasi GPS wajib dikirim untuk absensi.',
                'distance_meters' => null,
                'radius_meters' => $setting->radius_meters,
            ];
        }

        $distance = $this->calculateDistanceMeters(
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $setting->office_latitude,
            (float) $setting->office_longitude
        );

        $allowed = $distance <= $setting->radius_meters;

        return [
            'allowed' => $allowed,
            'enabled' => true,
            'action' => $action,
            'message' => $allowed ? 'Lokasi berada dalam radius kantor.' : 'Lokasi berada di luar radius kantor.',
            'distance_meters' => $distance,
            'radius_meters' => $setting->radius_meters,
            'office_name' => $setting->office_name,
            'office_latitude' => $setting->office_latitude,
            'office_longitude' => $setting->office_longitude,
        ];
    }

    private function validateQrToken(string $token, string $type): AttendanceQrToken|JsonResponse
    {
        $setting = AttendanceSetting::current();

        if (! $setting->is_qr_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR attendance sedang dinonaktifkan.',
            ], 422);
        }

        $qrToken = AttendanceQrToken::where('token', $token)->first();

        if (! $qrToken) {
            return response()->json([
                'success' => false,
                'message' => 'QR token tidak valid.',
            ], 422);
        }

        if (! $qrToken->is_active || $qrToken->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'QR token sudah tidak aktif atau sudah expired.',
            ], 422);
        }

        if (! in_array($qrToken->type, [$type, 'both'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'QR token tidak sesuai dengan tipe absensi.',
            ], 422);
        }

        return $qrToken;
    }

    private function calculateDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return (int) round($angle * $earthRadius);
    }

    private function calculateLateMinutes(?string $shiftStartTime, int $toleranceMinutes, Carbon $checkInTime): int
    {
        if (! $shiftStartTime) {
            return 0;
        }

        $scheduledStart = Carbon::parse(today()->format('Y-m-d').' '.substr($shiftStartTime, 0, 5));
        $allowedTime = $scheduledStart->copy()->addMinutes($toleranceMinutes);

        return $checkInTime->greaterThan($allowedTime)
            ? $allowedTime->diffInMinutes($checkInTime)
            : 0;
    }

    private function calculateOvertimeMinutes(Attendance $attendance): int
    {
        if (! $attendance->shift || ! $attendance->shift->end_time || ! $attendance->check_out_time) {
            return 0;
        }

        $shiftEnd = Carbon::parse($attendance->attendance_date->format('Y-m-d').' '.substr($attendance->shift->end_time, 0, 5));

        if ($attendance->shift->is_overnight && $shiftEnd->lessThanOrEqualTo(Carbon::parse($attendance->check_in_time))) {
            $shiftEnd->addDay();
        }

        return $attendance->check_out_time->greaterThan($shiftEnd)
            ? $shiftEnd->diffInMinutes($attendance->check_out_time)
            : 0;
    }

    private function transformAttendance(Attendance $attendance): array
    {
        $checkInRadius = $this->transformRadiusInfo($attendance->check_in_latitude, $attendance->check_in_longitude);
        $checkOutRadius = $this->transformRadiusInfo($attendance->check_out_latitude, $attendance->check_out_longitude);

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'shift_id' => $attendance->shift_id,
            'attendance_date' => optional($attendance->attendance_date)->format('Y-m-d'),
            'check_in_time' => optional($attendance->check_in_time)->format('H:i:s'),
            'check_out_time' => optional($attendance->check_out_time)->format('H:i:s'),
            'check_in_lat' => $attendance->check_in_latitude,
            'check_in_lng' => $attendance->check_in_longitude,
            'check_out_lat' => $attendance->check_out_latitude,
            'check_out_lng' => $attendance->check_out_longitude,
            'check_in_distance_meters' => $checkInRadius['distance_meters'],
            'check_out_distance_meters' => $checkOutRadius['distance_meters'],
            'is_check_in_within_radius' => $checkInRadius['is_within_radius'],
            'is_check_out_within_radius' => $checkOutRadius['is_within_radius'],
            'check_in_photo' => $attendance->check_in_photo,
            'check_out_photo' => $attendance->check_out_photo,
            'check_in_photo_url' => $attendance->check_in_photo ? asset('storage/'.$attendance->check_in_photo) : null,
            'check_out_photo_url' => $attendance->check_out_photo ? asset('storage/'.$attendance->check_out_photo) : null,
            'status' => $attendance->status,
            'note' => $attendance->note,
            'notes' => $attendance->note,
            'late_minutes' => $attendance->late_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'employee' => $attendance->relationLoaded('employee') && $attendance->employee ? [
                'id' => $attendance->employee->id,
                'name' => $attendance->employee->user->name ?? null,
                'department' => $attendance->employee->department ?? null,
                'position' => $attendance->employee->position ?? null,
            ] : null,
            'shift' => $attendance->relationLoaded('shift') && $attendance->shift ? [
                'id' => $attendance->shift->id,
                'name' => $attendance->shift->name ?? null,
                'code' => $attendance->shift->code ?? null,
                'start_time' => $attendance->shift->start_time ?? null,
                'end_time' => $attendance->shift->end_time ?? null,
                'late_tolerance' => $attendance->shift->late_tolerance ?? null,
                'is_overnight' => $attendance->shift->is_overnight ?? false,
            ] : null,
        ];
    }

    private function transformRadiusInfo($latitude, $longitude): array
    {
        $setting = AttendanceSetting::current();

        if (! $setting->is_radius_enabled || ! $setting->hasOfficeCoordinate() || is_null($latitude) || is_null($longitude)) {
            return [
                'distance_meters' => null,
                'is_within_radius' => null,
            ];
        }

        $distance = $this->calculateDistanceMeters(
            (float) $latitude,
            (float) $longitude,
            (float) $setting->office_latitude,
            (float) $setting->office_longitude
        );

        return [
            'distance_meters' => $distance,
            'is_within_radius' => $distance <= $setting->radius_meters,
        ];
    }

    private function transformQrToken(?AttendanceQrToken $token): ?array
    {
        if (! $token) {
            return null;
        }

        return [
            'id' => $token->id,
            'type' => $token->type,
            'expires_at' => optional($token->expires_at)->format('Y-m-d H:i:s'),
        ];
    }
}
