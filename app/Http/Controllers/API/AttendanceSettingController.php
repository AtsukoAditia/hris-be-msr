<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceQrToken;
use App\Models\AttendanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = AttendanceSetting::current();

        return response()->json([
            'success' => true,
            'message' => 'Setting absensi berhasil diambil.',
            'data' => $this->transformSetting($setting),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'office_name' => 'required|string|max:100',
            'office_latitude' => 'required|numeric|between:-90,90',
            'office_longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'is_radius_enabled' => 'required|boolean',
            'is_qr_enabled' => 'required|boolean',
            'qr_expiry_minutes' => 'required|integer|min:1|max:120',
        ]);

        $setting = AttendanceSetting::current();
        $setting->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Setting absensi berhasil diperbarui.',
            'data' => $this->transformSetting($setting->fresh()),
        ]);
    }

    public function generateQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:check_in,check_out,both',
            'expiry_minutes' => 'nullable|integer|min:1|max:120',
        ]);

        $setting = AttendanceSetting::current();

        if (!$setting->is_qr_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR attendance sedang dinonaktifkan.',
            ], 422);
        }

        AttendanceQrToken::where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        $expiryMinutes = (int) ($validated['expiry_minutes'] ?? $setting->qr_expiry_minutes ?? 5);
        $token = AttendanceQrToken::create([
            'token' => Str::random(64),
            'type' => $validated['type'] ?? 'both',
            'expires_at' => now()->addMinutes($expiryMinutes),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR attendance berhasil dibuat.',
            'data' => $this->transformQrToken($token),
        ], 201);
    }

    private function transformSetting(AttendanceSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'office_name' => $setting->office_name,
            'office_latitude' => $setting->office_latitude,
            'office_longitude' => $setting->office_longitude,
            'radius_meters' => $setting->radius_meters,
            'is_radius_enabled' => $setting->is_radius_enabled,
            'is_qr_enabled' => $setting->is_qr_enabled,
            'qr_expiry_minutes' => $setting->qr_expiry_minutes,
            'has_office_coordinate' => $setting->hasOfficeCoordinate(),
            'updated_at' => optional($setting->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function transformQrToken(AttendanceQrToken $token): array
    {
        return [
            'id' => $token->id,
            'token' => $token->token,
            'type' => $token->type,
            'expires_at' => optional($token->expires_at)->format('Y-m-d H:i:s'),
            'is_active' => $token->is_active,
        ];
    }
}
