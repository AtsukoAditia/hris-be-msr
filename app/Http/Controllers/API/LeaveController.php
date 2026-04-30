<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    /**
     * Admin/HR/Manager: semua leave request, atau employee: leave sendiri
     */
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Leave::with(['employee.user', 'approver']);

        // Non-admin/manager hanya bisa lihat leave sendiri
        if (!$user->isAdmin() && !$user->isManager()) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $leaves,
        ]);
    }

    /**
     * Employee: riwayat leave sendiri
     */
    public function my(Request $request): JsonResponse
    {
        $user     = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
                'data'    => [],
            ], 404);
        }

        $query = Leave::with(['approver'])
            ->where('employee_id', $employee->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $leaves,
        ]);
    }

    /**
     * Employee: sisa saldo cuti
     */
    public function balance(Request $request): JsonResponse
    {
        $user     = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $year    = $request->get('year', now()->year);
        $total   = 12; // default annual leave entitlement
        $used    = Leave::where('employee_id', $employee->id)
            ->where('leave_type', 'annual')
            ->where('status', Leave::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return response()->json([
            'success' => true,
            'data'    => [
                'total'     => $total,
                'used'      => (int) $used,
                'remaining' => max(0, $total - (int) $used),
                'year'      => $year,
            ],
        ]);
    }

    /**
     * Create leave request
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type' => 'required|in:annual,sick,personal,maternity,paternity',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string|max:1000',
        ]);

        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee profile not found'], 404);
        }

        $startDate  = \Carbon\Carbon::parse($validated['start_date']);
        $endDate    = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays  = $startDate->diffInWeekdays($endDate) + 1;

        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type'  => $validated['leave_type'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'total_days'  => $totalDays,
            'reason'      => $validated['reason'],
            'status'      => Leave::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dikirim.',
            'data'    => $leave->load(['employee.user']),
        ], 201);
    }

    /**
     * Show single leave
     */
    public function show(Leave $leave): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $leave->load(['employee.user', 'approver']),
        ]);
    }

    /**
     * Approve leave (manager/admin/hr)
     */
    public function approve(Request $request, Leave $leave): JsonResponse
    {
        if (!$leave->isPending()) {
            return response()->json(['success' => false, 'message' => 'Leave is not pending'], 422);
        }

        $leave->update([
            'status'      => Leave::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti disetujui.',
            'data'    => $leave->load(['employee.user', 'approver']),
        ]);
    }

    /**
     * Reject leave (manager/admin/hr)
     */
    public function reject(Request $request, Leave $leave): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if (!$leave->isPending()) {
            return response()->json(['success' => false, 'message' => 'Leave is not pending'], 422);
        }

        $leave->update([
            'status'           => Leave::STATUS_REJECTED,
            'approved_by'      => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti ditolak.',
            'data'    => $leave->load(['employee.user', 'approver']),
        ]);
    }

    /**
     * Cancel leave (employee sendiri, status masih pending)
     */
    public function destroy(Leave $leave): JsonResponse
    {
        $user = Auth::user();

        if (!$leave->isPending()) {
            return response()->json(['success' => false, 'message' => 'Hanya pengajuan pending yang bisa dibatalkan.'], 422);
        }

        // Pastikan yang cancel adalah pemilik leave atau admin
        if (!$user->isAdmin() && $leave->employee->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $leave->update(['status' => Leave::STATUS_CANCELLED]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dibatalkan.',
        ]);
    }
}
