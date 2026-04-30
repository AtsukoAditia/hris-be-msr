<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    // ==================== SHIFT TYPES CRUD ====================

    public function index(Request $request): JsonResponse
    {
        $query = Shift::query();
        if ($request->boolean('active_only', false)) {
            $query->active();
        }
        $shifts = $query->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $shifts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100|unique:shifts,name',
            'code'           => 'required|string|max:2|unique:shifts,code',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'description'    => 'nullable|string|max:500',
            'is_active'      => 'nullable|boolean',
        ]);
        $shift = Shift::create($validated);
        return response()->json(['success' => true, 'message' => 'Shift berhasil dibuat.', 'data' => $shift], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:100|unique:shifts,name,' . $shift->id,
            'code'           => 'sometimes|string|max:2|unique:shifts,code,' . $shift->id,
            'start_time'     => 'sometimes|date_format:H:i',
            'end_time'       => 'sometimes|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'description'    => 'nullable|string|max:500',
            'is_active'      => 'nullable|boolean',
        ]);
        $shift->update($validated);
        return response()->json(['success' => true, 'message' => 'Shift berhasil diupdate.', 'data' => $shift]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if (ShiftSchedule::where('shift_id', $shift->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Shift masih digunakan dalam jadwal, tidak bisa dihapus.'], 422);
        }
        $shift->delete();
        return response()->json(['success' => true, 'message' => 'Shift berhasil dihapus.']);
    }

    // ==================== SHIFT SCHEDULES (ASSIGN) ====================

    public function getSchedules(Request $request): JsonResponse
    {
        $query = ShiftSchedule::with(['employee.user', 'shift'])
            ->orderBy('schedule_date');

        if ($request->filled('date')) {
            $query->whereDate('schedule_date', $request->date);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereYear('schedule_date', $request->year)
                  ->whereMonth('schedule_date', $request->month);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function getMySchedule(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan.'], 404);
        }

        $query = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->orderBy('schedule_date');

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereYear('schedule_date', $request->year)
                  ->whereMonth('schedule_date', $request->month);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function assignShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'shift_id'      => 'required|exists:shifts,id',
            'schedule_date' => 'required|date|after_or_equal:today',
            'notes'         => 'nullable|string|max:500',
        ]);
        $validated['created_by'] = $request->user()->id;

        $schedule = ShiftSchedule::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'schedule_date' => $validated['schedule_date']],
            $validated
        );

        $schedule->load(['employee.user', 'shift']);
        return response()->json(['success' => true, 'message' => 'Shift berhasil di-assign.', 'data' => $schedule], 201);
    }

    public function removeSchedule(ShiftSchedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal shift berhasil dihapus.']);
    }
}
