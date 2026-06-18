<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function attendance(Request $request): JsonResponse
    {
        $query = $this->buildAttendanceQuery($request);
        $summaryQuery = clone $query;

        $perPage = min(max((int) $request->get('per_page', 30), 1), 100);
        $attendances = $query->latest('attendance_date')->latest('created_at')->paginate($perPage);
        $attendances->getCollection()->transform(fn ($attendance) => $this->transformAttendance($attendance));

        return response()->json([
            'success' => true,
            'message' => 'Laporan absensi berhasil diambil.',
            'data' => $attendances,
            'summary' => $this->getAttendanceSummary($summaryQuery),
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        $query = $this->buildLeaveQuery($request);
        $summaryQuery = clone $query;

        $perPage = min(max((int) $request->get('per_page', 30), 1), 100);
        $leaves = $query->latest('start_date')->latest('created_at')->paginate($perPage);
        $leaves->getCollection()->transform(fn ($leave) => $this->transformLeave($leave));

        return response()->json([
            'success' => true,
            'message' => 'Laporan cuti berhasil diambil.',
            'data' => $leaves,
            'summary' => $this->getLeaveSummary($summaryQuery),
        ]);
    }

    public function employee(Request $request): JsonResponse
    {
        $query = $this->buildEmployeeQuery($request);
        $summaryQuery = clone $query;

        $perPage = min(max((int) $request->get('per_page', 30), 1), 100);
        $employees = $query->latest()->paginate($perPage);
        $employees->getCollection()->transform(fn ($employee) => $this->transformEmployee($employee));

        return response()->json([
            'success' => true,
            'message' => 'Laporan karyawan berhasil diambil.',
            'data' => $employees,
            'summary' => [
                'total_active' => (clone $summaryQuery)->where('is_active', true)->count(),
                'total_inactive' => (clone $summaryQuery)->where('is_active', false)->count(),
                'total_records' => (clone $summaryQuery)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $type = $request->get('type', 'attendance');
        $format = $request->get('format', 'csv');

        if ($format !== 'csv') {
            return response()->json([
                'success' => false,
                'message' => 'Format export yang tersedia saat ini hanya CSV.',
            ], 422);
        }

        return match ($type) {
            'attendance' => $this->exportAttendanceCsv($request),
            'leave' => $this->exportLeaveCsv($request),
            'employee' => $this->exportEmployeeCsv($request),
            default => response()->json([
                'success' => false,
                'message' => 'Tipe laporan tidak valid.',
            ], 422),
        };
    }

    private function buildAttendanceQuery(Request $request)
    {
        $query = Attendance::with(['employee.user', 'shift']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department')) {
            $query->whereHas('employee', fn ($q) => $q->where('department', $request->department));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('employee.user', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        $this->applyDateFilter($query, $request, 'attendance_date');

        return $query;
    }

    private function buildLeaveQuery(Request $request)
    {
        $query = Leave::with(['employee.user', 'approver']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->status))->map(fn ($status) => trim($status))->filter()->values()->all();
            count($statuses) > 1 ? $query->whereIn('status', $statuses) : $query->where('status', $statuses[0] ?? $request->status);
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->filled('department')) {
            $query->whereHas('employee', fn ($q) => $q->where('department', $request->department));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('employee.user', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        $this->applyDateFilter($query, $request, 'start_date');

        return $query;
    }

    private function buildEmployeeQuery(Request $request)
    {
        $query = Employee::with(['user']);

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('nik', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query;
    }

    private function applyDateFilter($query, Request $request, string $column): void
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween($column, [$request->date_from, $request->date_to]);

            return;
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth($column, $request->month)->whereYear($column, $request->year);

            return;
        }

        $query->whereMonth($column, now()->month)->whereYear($column, now()->year);
    }

    private function getAttendanceSummary($query): array
    {
        return [
            'total_records' => (clone $query)->count(),
            'total_present' => (clone $query)->where('status', 'present')->count(),
            'total_late' => (clone $query)->where('status', 'late')->count(),
            'total_absent' => (clone $query)->where('status', 'absent')->count(),
            'total_leave' => (clone $query)->where('status', 'leave')->count(),
            'total_late_minutes' => (int) (clone $query)->sum('late_minutes'),
            'total_overtime_minutes' => (int) (clone $query)->sum('overtime_minutes'),
        ];
    }

    private function getLeaveSummary($query): array
    {
        return [
            'total_records' => (clone $query)->count(),
            'total_approved' => (clone $query)->where('status', 'approved')->count(),
            'total_pending' => (clone $query)->where('status', 'pending')->count(),
            'total_rejected' => (clone $query)->where('status', 'rejected')->count(),
            'total_cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'total_days' => (int) (clone $query)->sum('total_days'),
        ];
    }

    private function transformAttendance(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'attendance_date' => optional($attendance->attendance_date)->format('Y-m-d'),
            'employee_id' => $attendance->employee_id,
            'employee_name' => $attendance->employee?->user?->name,
            'department' => $attendance->employee?->department,
            'position' => $attendance->employee?->position,
            'shift_name' => $attendance->shift?->name,
            'check_in_time' => optional($attendance->check_in_time)->format('H:i:s'),
            'check_out_time' => optional($attendance->check_out_time)->format('H:i:s'),
            'status' => $attendance->status,
            'late_minutes' => $attendance->late_minutes ?? 0,
            'overtime_minutes' => $attendance->overtime_minutes ?? 0,
            'note' => $attendance->note,
        ];
    }

    private function transformLeave(Leave $leave): array
    {
        return [
            'id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'employee_name' => $leave->employee?->user?->name,
            'department' => $leave->employee?->department,
            'position' => $leave->employee?->position,
            'leave_type' => $leave->leave_type,
            'start_date' => optional($leave->start_date)->format('Y-m-d'),
            'end_date' => optional($leave->end_date)->format('Y-m-d'),
            'total_days' => $leave->total_days,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'approved_by' => $leave->approver?->name,
            'approved_at' => optional($leave->approved_at)->format('Y-m-d H:i:s'),
            'rejection_reason' => $leave->rejection_reason,
        ];
    }

    private function transformEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->user?->name,
            'email' => $employee->user?->email,
            'department' => $employee->department,
            'position' => $employee->position,
            'employment_type' => $employee->employment_type,
            'status' => $employee->is_active ? 'active' : 'inactive',
        ];
    }

    private function exportAttendanceCsv(Request $request): StreamedResponse
    {
        $rows = $this->buildAttendanceQuery($request)->latest('attendance_date')->get()->map(fn ($attendance) => $this->transformAttendance($attendance));

        return $this->streamCsv('attendance-report.csv', [
            'Tanggal', 'Nama', 'Departemen', 'Jabatan', 'Shift', 'Check In', 'Check Out', 'Status', 'Late Minutes', 'Overtime Minutes', 'Catatan',
        ], $rows->map(fn ($row) => [
            $row['attendance_date'], $row['employee_name'], $row['department'], $row['position'], $row['shift_name'], $row['check_in_time'], $row['check_out_time'], $row['status'], $row['late_minutes'], $row['overtime_minutes'], $row['note'],
        ])->all());
    }

    private function exportLeaveCsv(Request $request): StreamedResponse
    {
        $rows = $this->buildLeaveQuery($request)->latest('start_date')->get()->map(fn ($leave) => $this->transformLeave($leave));

        return $this->streamCsv('leave-report.csv', [
            'Nama', 'Departemen', 'Jabatan', 'Jenis Cuti', 'Tanggal Mulai', 'Tanggal Selesai', 'Total Hari', 'Status', 'Alasan', 'Approver', 'Approved At', 'Alasan Penolakan',
        ], $rows->map(fn ($row) => [
            $row['employee_name'], $row['department'], $row['position'], $row['leave_type'], $row['start_date'], $row['end_date'], $row['total_days'], $row['status'], $row['reason'], $row['approved_by'], $row['approved_at'], $row['rejection_reason'],
        ])->all());
    }

    private function exportEmployeeCsv(Request $request): StreamedResponse
    {
        $rows = $this->buildEmployeeQuery($request)->latest()->get()->map(fn ($employee) => $this->transformEmployee($employee));

        return $this->streamCsv('employee-report.csv', [
            'Employee Number', 'Nama', 'Email', 'Departemen', 'Jabatan', 'Tipe Karyawan', 'Status',
        ], $rows->map(fn ($row) => [
            $row['employee_number'], $row['name'], $row['email'], $row['department'], $row['position'], $row['employment_type'], $row['status'],
        ])->all());
    }

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
