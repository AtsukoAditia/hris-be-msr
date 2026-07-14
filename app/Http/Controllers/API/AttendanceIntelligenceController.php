<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AttendanceIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceIntelligenceController extends Controller
{
    public function __construct(
        private AttendanceIntelligenceService $service
    ) {}

    /**
     * GET /api/v1/attendance/who-is-in
     * Live who's in today view.
     */
    public function whoIsIn(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');
        $result = $this->service->whoIsIn($departmentId ? (int) $departmentId : null);

        return response()->json([
            'success' => true,
            'message' => 'Data siapa yang masuk hari ini.',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/attendance/monthly-summary
     * Monthly attendance summary.
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $result = $this->service->monthlySummary(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['department_id']) ? (int) $validated['department_id'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Rekap absensi bulanan.',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/attendance/anomalies
     * Anomaly detection.
     */
    public function anomalies(Request $request): JsonResponse
    {
        $months = (int) $request->query('months', 3);
        $months = max(1, min($months, 12));

        $result = $this->service->anomalies($months);

        return response()->json([
            'success' => true,
            'message' => 'Deteksi anomali absensi.',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/attendance/trend
     * Daily attendance trend.
     */
    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(7, min($days, 90));
        $departmentId = $request->query('department_id');

        $result = $this->service->trend(
            $days,
            $departmentId ? (int) $departmentId : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Tren absensi harian.',
            'data' => $result,
        ]);
    }
}
