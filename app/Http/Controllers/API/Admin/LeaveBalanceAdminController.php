<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceResource;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LeaveBalanceAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeaveBalance::with(['employee.user', 'leaveType'])
            ->orderByDesc('year')
            ->orderBy('employee_id');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->integer('leave_type_id'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        return LeaveBalanceResource::collection($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'total_days' => ['required_without:opening_days', 'integer', 'min:0', 'max:366'],
            'opening_days' => ['required_without:total_days', 'integer', 'min:0', 'max:366'],
            'used_days' => ['sometimes', 'integer', 'min:0', 'max:366'],
        ]);

        $existing = LeaveBalance::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('year', $validated['year'])
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Leave balance for this employee, leave type, and year already exists.',
            ], 409);
        }

        $openingDays = (int) ($validated['total_days'] ?? $validated['opening_days']);
        $usedDays = (int) ($validated['used_days'] ?? 0);

        $balance = LeaveBalance::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'year' => $validated['year'],
            'opening_days' => $openingDays,
            'used_days' => $usedDays,
            'remaining_days' => $openingDays - $usedDays,
        ]);

        LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'transaction_type' => 'initial',
            'change' => $openingDays,
            'balance_before' => 0,
            'balance_after' => $openingDays,
            'notes' => 'Initial balance',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Leave balance created successfully.',
            'data' => new LeaveBalanceResource($balance->load(['employee.user', 'leaveType'])),
        ], 201);
    }

    public function show(LeaveBalance $leaveBalance): LeaveBalanceResource
    {
        $leaveBalance->load(['employee.user', 'leaveType']);

        return new LeaveBalanceResource($leaveBalance);
    }

    public function update(Request $request, LeaveBalance $leaveBalance): JsonResponse
    {
        $validated = $request->validate([
            'total_days' => ['sometimes', 'integer', 'min:0', 'max:366'],
            'used_days' => ['sometimes', 'integer', 'min:0', 'max:366'],
            'remaining_days' => ['sometimes', 'integer', 'min:0', 'max:366'],
        ]);

        if (array_key_exists('total_days', $validated)) {
            $validated['opening_days'] = (int) $validated['total_days'];
            unset($validated['total_days']);
        }

        $balanceBefore = (int) $leaveBalance->opening_days;

        if (array_key_exists('opening_days', $validated)) {
            $validated['opening_days'] = (int) $validated['opening_days'];
        }

        if (array_key_exists('used_days', $validated)) {
            $validated['used_days'] = (int) $validated['used_days'];
        }

        if (array_key_exists('remaining_days', $validated)) {
            $validated['remaining_days'] = (int) $validated['remaining_days'];
        }

        if (
            isset($validated['opening_days']) &&
            isset($validated['used_days']) &&
            ! isset($validated['remaining_days'])
        ) {
            $validated['remaining_days'] = (int) $validated['opening_days'] - (int) $validated['used_days'];
        }

        $leaveBalance->update($validated);

        $balanceAfter = (int) $leaveBalance->fresh()->remaining_days;

        if ($balanceBefore !== $balanceAfter) {
            LeaveBalanceTransaction::create([
                'leave_balance_id' => $leaveBalance->id,
                'transaction_type' => 'manual_adjustment',
                'change' => $balanceAfter - $balanceBefore,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'notes' => 'Admin update',
                'created_by' => auth()->id(),
            ]);
        }

        return response()->json([
            'message' => 'Leave balance updated successfully.',
            'data' => new LeaveBalanceResource($leaveBalance->load(['employee.user', 'leaveType'])),
        ]);
    }

    public function destroy(LeaveBalance $leaveBalance): JsonResponse
    {
        $leaveBalance->delete();

        return response()->json([
            'message' => 'Leave balance deleted successfully.',
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_balance_id' => ['required', 'integer', 'exists:leave_balances,id'],
            'adjustment_days' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $balance = DB::transaction(function () use ($validated) {
            $balance = LeaveBalance::query()
                ->where('id', $validated['leave_balance_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (int) $balance->remaining_days;
            $newRemaining = $balanceBefore + (int) $validated['adjustment_days'];
            $balance->update([
                'opening_days' => (int) $balance->opening_days + (int) $validated['adjustment_days'],
                'remaining_days' => $newRemaining,
            ]);

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'transaction_type' => 'manual_adjustment',
                'change' => (int) $validated['adjustment_days'],
                'balance_before' => $balanceBefore,
                'balance_after' => $newRemaining,
                'notes' => $validated['reason'],
                'created_by' => auth()->id(),
            ]);

            return $balance;
        });

        return response()->json([
            'message' => 'Balance adjusted successfully.',
            'data' => new LeaveBalanceResource($balance->load(['employee.user', 'leaveType'])),
        ]);
    }
}
