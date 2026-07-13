<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class PayrollAdjustmentController extends Controller
{
    public function index(Payroll $payroll): AnonymousResourceCollection
    {
        $adjustments = $payroll->adjustments()->with('createdBy')->get();

        return JsonResource::collection($adjustments);
    }

    public function store(Request $request, Payroll $payroll): JsonResource
    {
        if (!in_array($payroll->status, [Payroll::STATUS_DRAFT, Payroll::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'payroll' => ['Adjustments can only be added to draft or submitted payroll.'],
            ]);
        }

        $validated = $request->validate([
            'type' => 'required|in:earning,deduction',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        $adjustment = $payroll->adjustments()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::log(ActivityAction::CREATE, PayrollAdjustment::class, $adjustment->id, [
            'payroll_id' => $payroll->id,
            'type' => $adjustment->type,
            'code' => $adjustment->code,
            'amount' => $adjustment->amount,
        ]);

        return new JsonResource($adjustment->load('createdBy'));
    }

    public function destroy(Request $request, PayrollAdjustment $adjustment): JsonResponse
    {
        $payroll = $adjustment->payroll;

        if (!in_array($payroll->status, [Payroll::STATUS_DRAFT, Payroll::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'payroll' => ['Adjustments can only be removed from draft or submitted payroll.'],
            ]);
        }

        ActivityLog::log(ActivityAction::DELETE, PayrollAdjustment::class, $adjustment->id, [
            'payroll_id' => $payroll->id,
            'type' => $adjustment->type,
            'code' => $adjustment->code,
            'amount' => $adjustment->amount,
        ]);

        $adjustment->delete();

        return response()->json(['message' => 'Adjustment deleted.']);
    }
}
