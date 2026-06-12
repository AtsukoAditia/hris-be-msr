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

class AttendanceActionController extends Controller
{
    private const METHOD_DEFAULT = 'default_photo_location';
    private const METHOD_QR = 'qr_radius_fallback';

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

    private function handleCheckIn(Request $request, bool $isQr): JsonResponse
    {
        $employee = $this->employee();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $existing = Attendance::where('employee_id', $employee->id)->whereDate('attendance_date', today())->first();

        if ($existing && $existing->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-in hari ini.'], 422);
        }

        $validated = $request->validate($this->rules($isQr));

        $qrToken = $isQr ? $this->validateQr($validated['qr_token'], 'check_in') : null;
        if ($qrToken instanceof JsonResponse) return $qrToken;

        $radius = $this->validateRadius($request, $isQr ? 'check_in_qr' : 'check_in');
        if (!$radius['allowed']) {
            return response()->json(['success' => false, 'message' => $radius['message'], 'data' => $radius], 422);
        }

        $shiftSchedule = ShiftSchedule::with('shift')->where('employee_id', $employee->id)->whereDate('schedule_date', today())->first();
        $shift = $shiftSchedule?->shift;
        $now = now();
        $lateMinutes = $this->lateMinutes($shift?->start_time, (int) ($shift?->late_tolerance ?? 15), $now);

        $attendance = $existing ?: new Attendance();
        $attendance->employee_id = $employee->id;
        $attendance->shift_id = $shift?->id;
        $attendance->attendance_date = today();
        $attendance->status = $lateMinutes > 0 ? 'late' : 'present';
        $attendance->late_minutes = $lateMinutes;
        $attendance->check_in_time = $now;
        $attendance->check_in_method = $isQr ? self::METHOD_QR : self::METHOD_DEFAULT;
        $attendance->check_in_latitude = $validated['latitude'];
        $attendance->check_in_longitude = $validated['longitude'];
        $attendance->note = $validated['note'] ?? $attendance->note;

        if ($request->hasFile('photo')) {
            $attendance->check_in_photo = $request->file('photo')->store('attendance/check-in', 'public');
        }

        $attendance->save();
        $attendance->load(['employee.user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => $this->successMessage('check_in', $isQr, $lateMinutes),
            'data' => array_merge($this->transform($attendance), [
                'attendance_method' => $attendance->check_in_method,
                'radius_validation' => $radius,
                'qr' => $isQr ? $this->transformQr($qrToken) : null,
            ]),
        ]);
    }

    private function handleCheckOut(Request $request, bool $isQr): JsonResponse
    {
        $employee = $this->employee();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 404);
        }

        $attendance = Attendance::with('shift')->where('employee_id', $employee->id)->whereDate('attendance_date', today())->first();

        if (!$attendance || !$attendance->check_in_time) {
            return response()->json(['success' => false, 'message' => 'Anda belum melakukan check-in hari ini.'], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-out hari ini.'], 422);
        }

        $validated = $request->validate($this->rules($isQr));

        $qrToken = $isQr ? $this->validateQr($validated['qr_token'], 'check_out') : null;
        if ($qrToken instanceof JsonResponse) return $qrToken;

        $radius = $this->validateRadius($request, $isQr ? 'check_out_qr' : 'check_out');
        if (!$radius['allowed']) {
            return response()->json(['success' => false, 'message' => $radius['message'], 'data' => $radius], 422);
        }

        $attendance->check_out_time = now();
        $attendance->check_out_method = $isQr ? self::METHOD_QR : self::METHOD_DEFAULT;
        $attendance->check_out_latitude = $validated['latitude'];
        $attendance->check_out_longitude = $validated['longitude'];
        $attendance->note = $validated['note'] ?? $attendance->note;

        if ($request->hasFile('photo')) {
            $attendance->check_out_photo = $request->file('photo')->store('attendance/check-out', 'public');
        }

        $attendance->overtime_minutes = $this->overtimeMinutes($attendance);
        $attendance->save();
        $attendance->load(['employee.user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => $this->successMessage('check_out', $isQr),
            'data' => array_merge($this->transform($attendance), [
                'attendance_method' => $attendance->check_out_method,
                'radius_validation' => $radius,
                'qr' => $isQr ? $this->transformQr($qrToken) : null,
            ]),
        ]);
    }

    private function rules(bool $isQr): array
    {
        return [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo' => $isQr ? 'nullable|image|mimes:jpg,jpeg,png,webp|max:12000' : 'required|image|mimes:jpg,jpeg,png,webp|max:12000',
            'note' => 'nullable|string|max:500',
            'qr_token' => $isQr ? 'required|string' : 'nullable|string',
        ];
    }

    private function validateRadius(Request $request, string $action): array
    {
        $setting = AttendanceSetting::current();

        if (!$setting->is_radius_enabled) {
            return ['allowed' => true, 'enabled' => false, 'action' => $action, 'message' => 'Validasi radius tidak aktif.', 'distance_meters' => null, 'radius_meters' => $setting->radius_meters];
        }

        if (!$setting->hasOfficeCoordinate()) {
            return ['allowed' => false, 'enabled' => true, 'action' => $action, 'message' => 'Koordinat kantor belum diatur.', 'distance_meters' => null, 'radius_meters' => $setting->radius_meters];
        }

        $distance = $this->distance((float) $request->latitude, (float) $request->longitude, (float) $setting->office_latitude, (float) $setting->office_longitude);
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

    private function validateQr(string $token, string $type): AttendanceQrToken|JsonResponse
    {
        $setting = AttendanceSetting::current();

        if (!$setting->is_qr_enabled) {
            return response()->json(['success' => false, 'message' => 'QR attendance sedang dinonaktifkan.'], 422);
        }

        $qr = AttendanceQrToken::where('token', $token)->first();

        if (!$qr) {
            return response()->json(['success' => false, 'message' => 'QR token tidak valid.'], 422);
        }

        if (!$qr->is_active || $qr->isExpired()) {
            return response()->json(['success' => false, 'message' => 'QR token sudah tidak aktif atau sudah expired.'], 422);
        }

        if (!in_array($qr->type, [$type, 'both'], true)) {
            return response()->json(['success' => false, 'message' => 'QR token tidak sesuai dengan tipe absensi.'], 422);
        }

        return $qr;
    }

    private function employee(): ?Employee
    {
        $user = request()->user();
        return $user ? Employee::with('user')->where('user_id', $user->id)->first() : null;
    }

    private function lateMinutes(?string $shiftStartTime, int $toleranceMinutes, Carbon $checkInTime): int
    {
        if (!$shiftStartTime) return 0;
        $scheduledStart = Carbon::parse(today()->format('Y-m-d') . ' ' . substr($shiftStartTime, 0, 5));
        $allowedTime = $scheduledStart->copy()->addMinutes($toleranceMinutes);
        return $checkInTime->greaterThan($allowedTime) ? $allowedTime->diffInMinutes($checkInTime) : 0;
    }

    private function overtimeMinutes(Attendance $attendance): int
    {
        if (!$attendance->shift || !$attendance->shift->end_time || !$attendance->check_out_time) return 0;
        $shiftEnd = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . substr($attendance->shift->end_time, 0, 5));
        if ($attendance->shift->is_overnight && $shiftEnd->lessThanOrEqualTo(Carbon::parse($attendance->check_in_time))) $shiftEnd->addDay();
        return $attendance->check_out_time->greaterThan($shiftEnd) ? $shiftEnd->diffInMinutes($attendance->check_out_time) : 0;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);
        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));
        return (int) round($angle * $earthRadius);
    }

    private function successMessage(string $action, bool $isQr, int $lateMinutes = 0): string
    {
        if ($isQr) return $action === 'check_in' ? ($lateMinutes > 0 ? 'Check-in via QR berhasil, status terlambat.' : 'Check-in via QR berhasil.') : 'Check-out via QR berhasil.';
        return $action === 'check_in' ? ($lateMinutes > 0 ? 'Check-in berhasil dengan foto dan lokasi, status terlambat.' : 'Check-in berhasil dengan foto dan lokasi.') : 'Check-out berhasil dengan foto dan lokasi.';
    }

    private function transform(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'shift_id' => $attendance->shift_id,
            'attendance_date' => optional($attendance->attendance_date)->format('Y-m-d'),
            'check_in_time' => optional($attendance->check_in_time)->format('H:i:s'),
            'check_out_time' => optional($attendance->check_out_time)->format('H:i:s'),
            'check_in_method' => $attendance->check_in_method,
            'check_out_method' => $attendance->check_out_method,
            'check_in_lat' => $attendance->check_in_latitude,
            'check_in_lng' => $attendance->check_in_longitude,
            'check_out_lat' => $attendance->check_out_latitude,
            'check_out_lng' => $attendance->check_out_longitude,
            'check_in_photo' => $attendance->check_in_photo,
            'check_out_photo' => $attendance->check_out_photo,
            'check_in_photo_url' => $attendance->check_in_photo ? asset('storage/' . $attendance->check_in_photo) : null,
            'check_out_photo_url' => $attendance->check_out_photo ? asset('storage/' . $attendance->check_out_photo) : null,
            'status' => $attendance->status,
            'note' => $attendance->note,
            'notes' => $attendance->note,
            'late_minutes' => $attendance->late_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'employee' => $attendance->employee ? ['id' => $attendance->employee->id, 'name' => $attendance->employee->user->name ?? null, 'department' => $attendance->employee->department ?? null, 'position' => $attendance->employee->position ?? null] : null,
            'shift' => $attendance->shift ? ['id' => $attendance->shift->id, 'name' => $attendance->shift->name ?? null, 'code' => $attendance->shift->code ?? null, 'start_time' => $attendance->shift->start_time ?? null, 'end_time' => $attendance->shift->end_time ?? null, 'late_tolerance' => $attendance->shift->late_tolerance ?? null, 'is_overnight' => $attendance->shift->is_overnight ?? false] : null,
        ];
    }

    private function transformQr(?AttendanceQrToken $qr): ?array
    {
        return $qr ? ['id' => $qr->id, 'type' => $qr->type, 'expires_at' => optional($qr->expires_at)->format('Y-m-d H:i:s')] : null;
    }
}
