<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeavePolicy\StoreLeavePolicyRequest;
use App\Http\Requests\LeavePolicy\UpdateLeavePolicyRequest;
use App\Http\Resources\LeavePolicyResource;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeavePolicyAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeavePolicy::with('leaveType')
            ->join('leave_types', 'leave_types.id', '=', 'leave_policies.leave_type_id')
            ->select('leave_policies.*')
            ->orderBy('leave_policies.year', 'desc')
            ->orderBy('leave_types.name');

        if ($request->filled('leave_type_id')) {
            $query->where('leave_policies.leave_type_id', $request->input('leave_type_id'));
        }

        if ($request->filled('year')) {
            $query->where('leave_policies.year', $request->input('year'));
        }

        if ($request->boolean('is_active')) {
            $query->where('leave_policies.is_active', true);
        }

        $policies = $query->paginate(50);

        return LeavePolicyResource::collection($policies);
    }

    public function store(StoreLeavePolicyRequest $request): JsonResponse
    {
        $policy = LeavePolicy::create($request->validated());

        return response()->json([
            'message' => 'Leave policy created successfully.',
            'data' => new LeavePolicyResource($policy->load('leaveType')),
        ], 201);
    }

    public function show(LeavePolicy $leavePolicy): LeavePolicyResource
    {
        return new LeavePolicyResource($leavePolicy->load('leaveType'));
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $leavePolicy->update($request->validated());

        return response()->json([
            'message' => 'Leave policy updated successfully.',
            'data' => new LeavePolicyResource($leavePolicy->load('leaveType')),
        ]);
    }

    public function destroy(LeavePolicy $leavePolicy): JsonResponse
    {
        $hasTransactions = LeaveBalance::query()
            ->where('leave_type_id', $leavePolicy->leave_type_id)
            ->where('year', $leavePolicy->year)
            ->whereHas('transactions')
            ->exists();

        if ($hasTransactions) {
            return response()->json([
                'message' => 'Cannot delete policy with existing balance transactions.',
            ], 409);
        }

        $leavePolicy->delete();

        return response()->json([
            'message' => 'Leave policy deleted successfully.',
        ]);
    }
}
