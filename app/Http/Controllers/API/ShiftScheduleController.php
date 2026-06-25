<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftSchedule\BulkStoreShiftScheduleRequest;
use App\Http\Requests\ShiftSchedule\CopyWeekRequest;
use App\Http\Resources\ShiftScheduleResource;
use App\Models\ShiftSchedule;
use App\Services\ShiftScheduleService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftScheduleController extends Controller
{
    public function __construct(
        private readonly ShiftScheduleService $scheduleService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ShiftSchedule::with(['employee.department', 'employee.branch', 'shift', 'createdBy']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->integer('shift_id'));
        }

        if ($request->filled('is_day_off')) {
            $query->where('is_day_off', $request->boolean('is_day_off'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('schedule_date', [$request->input('start_date'), $request->input('end_date')]);
        } elseif ($request->filled('schedule_date')) {
            $query->where('schedule_date', $request->input('schedule_date'));
        }

        $schedules = $query->orderBy('schedule_date')->paginate(50);

        return ShiftScheduleResource::collection($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'schedule_date' => 'required|date',
            'is_day_off' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->authorize('create', ShiftSchedule::class);

        try {
            $schedule = $this->scheduleService->assignShift(
                employeeId: $validated['employee_id'],
                shiftId: $validated['shift_id'] ?? null,
                date: $validated['schedule_date'],
                isDayOff: $validated['is_day_off'] ?? false,
                notes: $validated['notes'] ?? null,
                createdBy: $request->user()->id,
            );

            return (new ShiftScheduleResource($schedule->load(['employee.department', 'employee.branch', 'shift', 'createdBy'])))
                ->response()
                ->setStatusCode(201);
        } catch (BusinessValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(ShiftSchedule $shiftSchedule): ShiftScheduleResource
    {
        $shiftSchedule->load(['employee.department', 'employee.branch', 'shift', 'createdBy']);

        return new ShiftScheduleResource($shiftSchedule);
    }

    public function update(Request $request, ShiftSchedule $shiftSchedule): JsonResponse
    {
        $this->authorize('update', $shiftSchedule);

        $validated = $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'is_day_off' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $updated = $this->scheduleService->assignShift(
                employeeId: $shiftSchedule->employee_id,
                shiftId: $validated['shift_id'] ?? $shiftSchedule->shift_id,
                date: $shiftSchedule->schedule_date,
                isDayOff: $validated['is_day_off'] ?? $shiftSchedule->is_day_off,
                notes: $validated['notes'] ?? $shiftSchedule->notes,
                createdBy: $request->user()->id,
                existingId: $shiftSchedule->id,
            );

            return (new ShiftScheduleResource($updated->load(['employee.department', 'employee.branch', 'shift', 'createdBy'])))
                ->response();
        } catch (BusinessValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(ShiftSchedule $shiftSchedule): JsonResponse
    {
        $this->authorize('delete', $shiftSchedule);

        $shiftSchedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule berhasil dihapus',
        ]);
    }

    public function bulkStore(BulkStoreShiftScheduleRequest $request): JsonResponse
    {
        $result = $this->scheduleService->bulkAssign(
            employeeIds: $request->input('employee_ids'),
            schedules: $request->input('schedules'),
            createdBy: $request->user()->id,
        );

        $created = EloquentCollection::make($result['created']);
        $created->load(['employee.department', 'employee.branch', 'shift', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => count($result['created']).' schedule berhasil dibuat',
            'data' => ShiftScheduleResource::collection($created),
            'errors' => $result['errors'],
        ], count($result['errors']) > 0 ? 207 : 201);
    }

    public function copyWeek(CopyWeekRequest $request): JsonResponse
    {
        $result = $this->scheduleService->copyWeek(
            sourceStartDate: $request->input('source_start_date'),
            targetStartDate: $request->input('target_start_date'),
            employeeIds: $request->input('employee_ids'),
            createdBy: $request->user()->id,
        );

        $created = EloquentCollection::make($result['created']);
        $created->load(['employee.department', 'employee.branch', 'shift', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => count($result['created']).' schedule berhasil di-copy',
            'data' => ShiftScheduleResource::collection($created),
            'errors' => $result['errors'],
        ], count($result['errors']) > 0 ? 207 : 201);
    }

    public function mySchedule(Request $request): AnonymousResourceCollection
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return ShiftScheduleResource::collection(collect());
        }

        $query = ShiftSchedule::with(['shift', 'createdBy'])
            ->where('employee_id', $employee->id);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('schedule_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $schedules = $query->orderBy('schedule_date')->paginate(50);

        return ShiftScheduleResource::collection($schedules);
    }

    public function teamSchedule(Request $request): AnonymousResourceCollection
    {
        $manager = $request->user()->employee;

        if (! $manager) {
            return ShiftScheduleResource::collection(collect());
        }

        $teamIds = $manager->directReports()->pluck('id')->toArray();
        $teamIds[] = $manager->id;

        $query = ShiftSchedule::with(['employee.department', 'employee.branch', 'shift', 'createdBy'])
            ->whereIn('employee_id', $teamIds);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('schedule_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $schedules = $query->orderBy('schedule_date')->paginate(50);

        return ShiftScheduleResource::collection($schedules);
    }
}