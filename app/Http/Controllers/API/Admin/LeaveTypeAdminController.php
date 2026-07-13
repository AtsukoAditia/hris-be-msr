<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveType\StoreLeaveTypeRequest;
use App\Http\Requests\LeaveType\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LeaveTypeAdminController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = LeaveType::query()
            ->orderBy('name')
            ->paginate(50);

        return LeaveTypeResource::collection($types);
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        try {
            $type = LeaveType::create($request->validated());

            return response()->json([
                'message' => 'Leave type created successfully.',
                'data' => new LeaveTypeResource($type),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create leave type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(LeaveType $leaveType): LeaveTypeResource
    {
        return new LeaveTypeResource($leaveType);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        try {
            $leaveType->update($request->validated());

            return response()->json([
                'message' => 'Leave type updated successfully.',
                'data' => new LeaveTypeResource($leaveType),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update leave type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(LeaveType $leaveType): JsonResponse|Response
    {
        try {
            if ($leaveType->leaves()->exists()) {
                return response()->json([
                    'message' => 'Cannot delete leave type with existing leaves.',
                ], 409);
            }

            if ($leaveType->policies()->exists()) {
                return response()->json([
                    'message' => 'Cannot delete leave type with existing policies. Delete policies first.',
                ], 409);
            }

            $leaveType->delete();

            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete leave type: ' . $e->getMessage(),
            ], 500);
        }
    }
}
