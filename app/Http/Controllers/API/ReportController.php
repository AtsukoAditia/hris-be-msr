<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Attendance report.
     */
    public function attendance(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'shift']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->has('month') && $request->has('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        } else {
            // Default: current month
            $query->whereMonth('date', now()->month)
                  ->whereYear('date', now()->year);
        }

        $attendances = $query->orderBy('date', 'desc')
            ->paginate($request->get('per_page', 30));

        // Summary stats
        $totalPresent = $attendances->where('status', 'present')->count();
        $totalLate = $attendances->where('status', 'late')->count();
        $totalAbsent = $attendances->where('status', 'absent')->count();

        return response()->json([
            'success' => true,
            'data' => $attendances,
            'summary' => [
                'total_present' => $totalPresent,
                'total_late' => $totalLate,
                'total_absent' => $totalAbsent,
                'total_records' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Leave report.
     */
    public function leave(Request $request): JsonResponse
    {
        $query = Leave::with(['employee.user', 'approver']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by leave type
        if ($request->has('leave_type')) {
            $query->where('leave_type', $request->leave_type);
feat: add ReportController.php - attendance and leave reports
        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('month') && $request->has('year')) {
            $query->whereMonth('start_date', $request->month)
                  ->whereYear('start_date', $request->year);
        } else {
            $query->whereYear('start_date', now()->year);
        }

        $leaves = $query->orderBy('start_date', 'desc')
            ->paginate($request->get('per_page', 30));

        // Summary stats
        $totalApproved = $leaves->where('status', 'approved')->count();
        $totalPending = $leaves->where('status', 'pending')->count();
        $totalRejected = $leaves->where('status', 'rejected')->count();

        return response()->json([
            'success' => true,
            'data' => $leaves,
            'summary' => [
                'total_approved' => $totalApproved,
                'total_pending' => $totalPending,
                'total_rejected' => $totalRejected,
                'total_records' => $leaves->total(),
            ],
        ]);
    }
}
