<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;
        $employee = $user->employee;
        $today = today();

        if (in_array($role, ['admin', 'hr', 'manager'], true)) {
            $data = [
                'role' => $role,
                'scope' => $role === 'manager' ? 'approval' : 'global',
                'cards' => [
                    'total_employees' => Employee::where('is_active', true)->count(),
                    'present_today' => Attendance::whereDate('attendance_date', $today)->where('status', 'present')->count(),
                    'late_today' => Attendance::whereDate('attendance_date', $today)->where('status', 'late')->count(),
                    'leave_today' => Leave::where('status', Leave::STATUS_APPROVED)->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
                    'pending_leave_requests' => Leave::where('status', Leave::STATUS_PENDING)->count(),
                    'total_shifts' => Shift::where('is_active', true)->count(),
                ],
                'recent_attendances' => Attendance::with(['employee.user', 'shift'])->latest('attendance_date')->latest('created_at')->limit(5)->get(),
                'recent_leaves' => Leave::with(['employee.user', 'approver'])->latest()->limit(5)->get(),
                'today_attendance' => null,
                'leave_balance' => null,
                'today_shift' => null,
            ];

            return response()->json(['success' => true, 'message' => 'Ringkasan dashboard berhasil diambil.', 'data' => $data]);
        }

        if (!$employee) {
            return response()->json([
                'success' => true,
                'message' => 'Ringkasan dashboard berhasil diambil.',
                'data' => [
                    'role' => 'employee',
                    'scope' => 'personal',
                    'cards' => ['attendance_status' => 'not_found', 'check_in_time' => null, 'check_out_time' => null, 'remaining_leave' => 12, 'pending_leave_requests' => 0],
                    'recent_attendances' => [],
                    'recent_leaves' => [],
                    'today_attendance' => null,
                    'leave_balance' => ['total' => 12, 'used' => 0, 'remaining' => 12, 'year' => (int) now()->year],
                    'today_shift' => null,
                ],
            ]);
        }

        $attendance = Attendance::with('shift')->where('employee_id', $employee->id)->whereDate('attendance_date', $today)->first();
        $shiftSchedule = ShiftSchedule::with('shift')->where('employee_id', $employee->id)->whereDate('schedule_date', $today)->first();
        $usedLeave = Leave::where('employee_id', $employee->id)->where('leave_type', 'annual')->where('status', Leave::STATUS_APPROVED)->whereYear('start_date', now()->year)->sum('total_days');

        $data = [
            'role' => 'employee',
            'scope' => 'personal',
            'cards' => [
                'attendance_status' => $attendance?->status ?? 'not_checked_in',
                'check_in_time' => optional($attendance?->check_in_time)->format('H:i:s'),
                'check_out_time' => optional($attendance?->check_out_time)->format('H:i:s'),
                'remaining_leave' => max(0, 12 - (int) $usedLeave),
                'pending_leave_requests' => Leave::where('employee_id', $employee->id)->where('status', Leave::STATUS_PENDING)->count(),
            ],
            'recent_attendances' => Attendance::with('shift')->where('employee_id', $employee->id)->latest('attendance_date')->latest('created_at')->limit(5)->get(),
            'recent_leaves' => Leave::with('approver')->where('employee_id', $employee->id)->latest()->limit(5)->get(),
            'today_attendance' => $attendance,
            'leave_balance' => ['total' => 12, 'used' => (int) $usedLeave, 'remaining' => max(0, 12 - (int) $usedLeave), 'year' => (int) now()->year],
            'today_shift' => $shiftSchedule,
        ];

        return response()->json(['success' => true, 'message' => 'Ringkasan dashboard berhasil diambil.', 'data' => $data]);
    }
}
