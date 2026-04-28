<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    /**
     * Display a listing of leave requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Leave::with(['employee.user', 'approver']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // If not admin, only show own leaves
        if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('manager')) {
            $query->whereHas('employee', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $leaves,
        ]);
    }

    /**
     * Store a newly created leave request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type' => 'required|in:annual,sick,personal,maternity,paternity',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee profile not found'], 404);
        }

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInWeekdays($endDate) + 1;

        $leave = Leave::create([
            ...$validated,
            'employee_id' => $employee->id,
            'total_days' => $totalDays,
            'status' => Leave::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data' => $leave->load(['employee.user']),
        ], 201);
    }

    /**
     * Display the specified leave.
     */
    public function show(Leave $leave): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $leave->load(['employee.user', 'approver']),
        ]);
    }

    /**
     * Approve the leave request.
     */
    public function approve(Request $request, Leave $leave): JsonResponse
    {
        if (!$leave->isPending()) {
            return response()->json(['success' => false, 'message' => 'Leave is not pending'], 422);
        }

        $leave->update([
            'status' => Leave::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave approved successfully',
            'data' => $leave->load(['employee.user', 'approver']),
        ]);
    }

    /**
     * Reject the leave request.
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
            'status' => Leave::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected',
            'data' => $leave->load(['employee.user', 'approver']),
        ]);
    }
}
