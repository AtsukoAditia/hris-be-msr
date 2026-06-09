<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Attendance report.
     */
    public function attendance(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'shift']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->date_from, $request->date_to]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('attendance_date', $request->month)
                ->whereYear('attendance_date', $request->year);
        } else {
            $query->whereMonth('attendance_date', now()->month)
                ->whereYear('attendance_date', now()->year);
        }

        $attendances = $query->latest('attendance_date')
            ->paginate($request->get('per_page', 30));

        $collection = $attendances->getCollection();

        return response()->json([
            'success' => true,
            'message' => 'Laporan absensi berhasil diambil.',
            'data' => $attendances,
            'summary' => [
                'total_present' => $collection->where('status', 'present')->count(),
                'total_late' => $collection->where('status', 'late')->count(),
                'total_absent' => $collection->where('status', 'absent')->count(),
                'total_leave' => $collection->where('status', 'leave')->count(),
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

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('start_date', [$request->date_from, $request->date_to]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('start_date', $request->month)
                ->whereYear('start_date', $request->year);
        } else {
            $query->whereYear('start_date', now()->year);
        }

        $leaves = $query->latest('start_date')
            ->paginate($request->get('per_page', 30));

        $collection = $leaves->getCollection();

        return response()->json([
            'success' => true,
            'message' => 'Laporan cuti berhasil diambil.',
            'data' => $leaves,
            'summary' => [
                'total_approved' => $collection->where('status', 'approved')->count(),
                'total_pending' => $collection->where('status', 'pending')->count(),
                'total_rejected' => $collection->where('status', 'rejected')->count(),
                'total_records' => $leaves->total(),
            ],
        ]);
    }

    /**
     * Employee report.
     */
    public function employee(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'shift']);

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', '%' . $search . '%')
                    ->orWhere('nik', 'like', '%' . $search . '%')
                    ->orWhere('department', 'like', '%' . $search . '%')
                    ->orWhere('position', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $employees = $query->latest()
            ->paginate($request->get('per_page', 30));

        $collection = $employees->getCollection();

        return response()->json([
            'success' => true,
            'message' => 'Laporan karyawan berhasil diambil.',
            'data' => $employees,
            'summary' => [
                'total_active' => $collection->where('is_active', true)->count(),
                'total_inactive' => $collection->where('is_active', false)->count(),
                'total_records' => $employees->total(),
            ],
        ]);
    }

    /**
     * Export report placeholder.
     */
    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur export laporan belum tersedia.',
        ], 501);
    }
}
