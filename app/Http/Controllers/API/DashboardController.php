<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $isManager = $user->hasRole('manager');

        if ($isAdmin || $isManager) {
            return $this->adminStats();
        }

        return $this->employeeStats($user);
    }

    /**
     * Stats for admin/manager.
     */
    private function adminStats(): JsonResponse
    {
        $totalEmployees = Employee::where('status', 'active')->count();
        $presentToday = Attendance::today()->count();
        $pendingLeaves = Leave::pending()->count();
        $absentToday = $totalEmployees - $presentToday;

        $recentAttendances = Attendance::with(['employee.user'])
            ->today()
            ->latest()
            ->take(10)
            ->get();

        $recentLeaves = Leave::with(['employee.user'])
            ->pending()
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_employees' => $totalEmployees,
                    'present_today' => $presentToday,
                    'absent_today' => $absentToday,
                    'pending_leaves' => $pendingLeaves,
                ],
                'recent_attendances' => $recentAttendances,
                'recent_leaves' => $recentLeaves,
            ],
        ]);
    }

    /**
     * Stats for regular employee.
     */
    private function employeeStats($user): JsonResponse
    {
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $todayAttendance = Attendance::where('employee_id', $employee->id)->today()->first();
        $monthAttendances = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->count();
        $pendingLeaves = Leave::where('employee_id', $employee->id)
            ->pending()->count();
        $approvedLeaves = Leave::where('employee_id', $employee->id)
            ->approved()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employee->load('shift'),
                'today_attendance' => $todayAttendance,
                'stats' => [
                    'this_month_attendances' => $monthAttendances,
                    'pending_leaves' => $pendingLeaves,
                    'approved_leaves' => $approvedLeaves,
                ],
            ],
        ]);
    }
}
