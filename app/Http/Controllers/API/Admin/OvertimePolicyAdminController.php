<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OvertimePolicy\StoreOvertimePolicyRequest;
use App\Http\Requests\OvertimePolicy\UpdateOvertimePolicyRequest;
use App\Http\Resources\OvertimePolicyResource;
use App\Models\OvertimePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimePolicyAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $policies = OvertimePolicy::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OvertimePolicyResource::collection($policies);
    }

    public function store(StoreOvertimePolicyRequest $request): JsonResponse
    {
        $policy = OvertimePolicy::create($request->validated());

        return (new OvertimePolicyResource($policy))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, OvertimePolicy $overtimePolicy): OvertimePolicyResource
    {
        return new OvertimePolicyResource($overtimePolicy);
    }

    public function update(UpdateOvertimePolicyRequest $request, OvertimePolicy $overtimePolicy): OvertimePolicyResource
    {
        $overtimePolicy->update($request->validated());

        return new OvertimePolicyResource($overtimePolicy->fresh());
    }

    public function destroy(Request $request, OvertimePolicy $overtimePolicy): JsonResponse
    {
        if ($overtimePolicy->overtimeRequests()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a policy that has associated overtime requests.',
            ], 409);
        }

        $overtimePolicy->delete();

        return response()->json(['message' => 'Overtime policy deleted.']);
    }
}