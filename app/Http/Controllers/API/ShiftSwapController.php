<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftSwap\StoreShiftSwapRequest;
use App\Http\Resources\ShiftScheduleResource;
use App\Http\Resources\ShiftSwapResource;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\Employee;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftSwapController extends Controller
{
    public function show(ShiftSwapRequest $shiftSwapRequest): ShiftSwapResource
    {
        return new ShiftSwapResource($shiftSwapRequest->load([
            'requester', 'target', 'requesterSchedule.shift', 'targetSchedule.shift', 'reviewedBy',
        ]));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ShiftSwapRequest::with(['requester', 'target', 'requesterSchedule', 'targetSchedule', 'reviewedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $user = $request->user();

        // Non-admin: show own requests + incoming
        if (! in_array($user->role, ['admin', 'hr'])) {
            $employeeId = $user->employee?->id;
            $query->where(function ($q) use ($employeeId) {
                $q->where('requester_id', $employeeId)
                  ->orWhere('target_id', $employeeId);
            });
        }

        $swaps = $query->orderByDesc('created_at')->paginate(20);

        return ShiftSwapResource::collection($swaps);
    }

    public function store(StoreShiftSwapRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['requester_id'] = $request->user()->employee?->id;

        if (! $data['requester_id']) {
            return response()->json(['success' => false, 'message' => 'User has no linked employee.'], 422);
        }

        $swap = ShiftSwapRequest::create($data);

        // Notify the target employee
        $targetEmp = Employee::find($data['target_id']);
        if ($targetEmp) {
            NotificationService::create(
                $targetEmp->user_id,
                'shift_swap_requested',
                'Permintaan Tukar Shift',
                "{$request->user()->employee?->full_name} mengajukan tukar shift dengan Anda.",
                '🔄',
                '/my-schedule',
            );
        }

        return (new ShiftSwapResource($swap->load(['requester', 'target', 'requesterSchedule', 'targetSchedule'])))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(ShiftSwapRequest $shiftSwapRequest, Request $request): JsonResponse
    {
        if ($shiftSwapRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Swap request is not pending.'], 409);
        }

        $shiftSwapRequest->approve($request->user(), $request->input('review_notes'));

        // Execute the swap if target schedule provided
        if ($shiftSwapRequest->target_schedule_id) {
            $this->executeSwap($shiftSwapRequest);
        }

        $shiftSwapRequest->load(['requester', 'target', 'requesterSchedule', 'targetSchedule']);
        NotificationService::create(
            $shiftSwapRequest->requester->user_id,
            'shift_swap_approved',
            'Tukar Shift Disetujui',
            'Permintaan tukar shift Anda telah disetujui.',
            '✅',
            '/my-schedule',
        );

        return (new ShiftSwapResource($shiftSwapRequest))
            ->response();
    }

    public function reject(ShiftSwapRequest $shiftSwapRequest, Request $request): JsonResponse
    {
        if ($shiftSwapRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Swap request is not pending.'], 409);
        }

        $shiftSwapRequest->reject($request->user(), $request->input('review_notes'));

        NotificationService::create(
            $shiftSwapRequest->requester->user_id,
            'shift_swap_rejected',
            'Tukar Shift Ditolak',
            'Permintaan tukar shift Anda ditolak: ' . ($request->input('review_notes') ?: '-'),
            '❌',
            '/my-schedule',
        );

        return (new ShiftSwapResource($shiftSwapRequest->load(['requester', 'target', 'requesterSchedule', 'targetSchedule'])))
            ->response();
    }

    public function myRequests(Request $request): AnonymousResourceCollection
    {
        $employeeId = $request->user()->employee?->id;

        $swaps = ShiftSwapRequest::with(['requester', 'target', 'requesterSchedule', 'targetSchedule'])
            ->where('requester_id', $employeeId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return ShiftSwapResource::collection($swaps);
    }

    public function incomingRequests(Request $request): AnonymousResourceCollection
    {
        $employeeId = $request->user()->employee?->id;

        $swaps = ShiftSwapRequest::with(['requester', 'target', 'requesterSchedule', 'targetSchedule'])
            ->where('target_id', $employeeId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return ShiftSwapResource::collection($swaps);
    }

    public function cancel(ShiftSwapRequest $shiftSwapRequest, Request $request): JsonResponse
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        // Only requester or admin/hr can cancel
        if ($shiftSwapRequest->requester_id !== $employeeId && ! in_array($user->role, ['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($shiftSwapRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be cancelled.'], 409);
        }

        $shiftSwapRequest->update([
            'status' => ShiftSwapRequest::STATUS_CANCELLED,
        ]);

        return (new ShiftSwapResource($shiftSwapRequest->load(['requester', 'target', 'requesterSchedule', 'targetSchedule'])))
            ->response();
    }

    private function executeSwap(ShiftSwapRequest $swap): void
    {
        $requesterSchedule = ShiftSchedule::find($swap->requester_schedule_id);
        $targetSchedule = ShiftSchedule::find($swap->target_schedule_id);

        if (! $requesterSchedule || ! $targetSchedule) {
            return;
        }

        // Swap shift assignments
        $tempShift = $requesterSchedule->shift_id;
        $tempDayOff = $requesterSchedule->is_day_off;

        $requesterSchedule->update([
            'shift_id' => $targetSchedule->shift_id,
            'is_day_off' => $targetSchedule->is_day_off,
        ]);

        $targetSchedule->update([
            'shift_id' => $tempShift,
            'is_day_off' => $tempDayOff,
        ]);
    }
}
