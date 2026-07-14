<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Department cost breakdown (salary, BPJS, headcount).
     * GET /api/v1/analytics/department-costs
     */
    public function departmentCosts(Request $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $payrolls = Payroll::query()
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payrolls.payroll_period_id')
            ->whereYear('payroll_periods.start_date', $year)
            ->whereMonth('payroll_periods.start_date', $month)
            ->selectRaw("
                employees.department,
                COUNT(*) as headcount,
                SUM(payrolls.basic_salary) as total_basic,
                SUM(payrolls.net_salary) as total_net,
                SUM(payrolls.bpjs_jkk + payrolls.bpjs_jkm + payrolls.bpjs_jht_er + payrolls.bpjs_jp_er) as total_bpjs_er,
                SUM(payrolls.bpjs_jht_ee + payrolls.bpjs_jp_ee + payrolls.bpjs_kes_ee) as total_bpjs_ee,
                SUM(payrolls.pph21) as total_tax
            ")
            ->groupBy('employees.department')
            ->orderByDesc('total_net')
            ->get();

        return response()->json(['data' => $payrolls]);
    }

    /**
     * Attendance summary by department for a period.
     * GET /api/v1/analytics/attendance-summary
     */
    public function attendanceSummary(Request $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $summary = Attendance::query()
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereYear('attendances.attendance_date', $year)
            ->whereMonth('attendances.attendance_date', $month)
            ->selectRaw("
                employees.department,
                COUNT(*) as total_records,
                SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendances.status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                SUM(attendances.late_minutes) as total_late_minutes,
                SUM(attendances.overtime_minutes) as total_overtime_minutes,
                ROUND(AVG(attendances.late_minutes), 1) as avg_late_minutes,
                COUNT(DISTINCT attendances.employee_id) as active_employees
            ")
            ->groupBy('employees.department')
            ->orderBy('employees.department')
            ->get();

        return response()->json(['data' => $summary]);
    }

    /**
     * Executive summary — top-level metrics for dashboard.
     * GET /api/v1/analytics/executive-summary
     */
    public function executiveSummary(): JsonResponse
    {
        $now = now();
        $month = $now->month;
        $year = $now->year;

        $headcount = Employee::where('is_active', true)->count();
        $newHires = Employee::whereMonth('created_at', $month)->whereYear('created_at', $year)->count();

        $attendanceStats = Attendance::whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->selectRaw("
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(late_minutes) as total_late_minutes,
                SUM(overtime_minutes) as total_overtime_minutes
            ")
            ->first();

        $leaveStats = Leave::whereMonth('start_date', $month)
            ->whereYear('start_date', $year)
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'approved' THEN total_days ELSE 0 END) as total_approved_days
            ")
            ->first();

        $latestPayroll = PayrollPeriod::where('status', 'closed')->latest('end_date')->first();
        $payrollTotal = $latestPayroll
            ? Payroll::where('payroll_period_id', $latestPayroll->id)
                ->selectRaw('SUM(net_salary) as total_net, COUNT(*) as count, SUM(pph21) as total_tax, SUM(bpjs_jht_ee + bpjs_jp_ee + bpjs_kes_ee) as total_bpjs')
                ->first()
            : null;

        return response()->json([
            'headcount' => [
                'total_active' => $headcount,
                'new_hires_this_month' => $newHires,
            ],
            'attendance' => [
                'total_records' => (int) ($attendanceStats->total_records ?? 0),
                'present' => (int) ($attendanceStats->present ?? 0),
                'late' => (int) ($attendanceStats->late ?? 0),
                'absent' => (int) ($attendanceStats->absent ?? 0),
                'attendance_rate' => $attendanceStats->total_records > 0
                    ? round(($attendanceStats->present + $attendanceStats->late) / $attendanceStats->total_records * 100, 1)
                    : 0,
                'total_late_minutes' => (int) ($attendanceStats->total_late_minutes ?? 0),
                'total_overtime_minutes' => (int) ($attendanceStats->total_overtime_minutes ?? 0),
            ],
            'leave' => [
                'total_requests' => (int) ($leaveStats->total_requests ?? 0),
                'approved' => (int) ($leaveStats->approved ?? 0),
                'pending' => (int) ($leaveStats->pending ?? 0),
                'rejected' => (int) ($leaveStats->rejected ?? 0),
                'total_approved_days' => (int) ($leaveStats->total_approved_days ?? 0),
            ],
            'payroll' => [
                'period' => $latestPayroll?->name,
                'total_net' => (float) ($payrollTotal->total_net ?? 0),
                'total_tax' => (float) ($payrollTotal->total_tax ?? 0),
                'total_bpjs' => (float) ($payrollTotal->total_bpjs ?? 0),
                'employees_paid' => (int) ($payrollTotal->count ?? 0),
            ],
        ]);
    }

    /**
     * Department headcount distribution.
     * GET /api/v1/analytics/headcount
     */
    public function headcount(): JsonResponse
    {
        $data = Employee::where('is_active', true)
            ->selectRaw('department, COUNT(*) as count')
            ->groupBy('department')
            ->orderByDesc('count')
            ->get();

        $total = $data->sum('count');
        $data->each(fn ($item) => $item->percentage = $total > 0 ? round($item->count / $total * 100, 1) : 0);

        return response()->json([
            'data' => $data,
            'total' => $total,
        ]);
    }
}
