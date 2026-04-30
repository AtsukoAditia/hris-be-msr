<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ShiftScheduleController extends Controller
{
    // ==================== SHIFT SCHEDULE CRUD ====================
    
    public function index(Request $request): JsonResponse
    {
        $query = ShiftSchedule::with(['employee', 'shift', 'createdBy']);
        
        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        // Filter by shift
        if ($request->has('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }
        
        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('schedule_date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('schedule_date')) {
            $query->where('schedule_date', $request->schedule_date);
        }
        
        $schedules = $query->orderBy('schedule_date', 'desc')->get();
        
        return response()->json(['success' => true, 'data' => $schedules]);
    }
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'schedule_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        
        $validated['created_by'] = Auth::id();
        
        try {
            $schedule = ShiftSchedule::create($validated);
            $schedule->load(['employee', 'shift', 'createdBy']);
            
            return response()->json([
                'success' => true,
                'message' => 'Shift berhasil di-assign',
                'data' => $schedule
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign shift: ' . $e->getMessage()
            ], 400);
        }
    }
    
    public function show($id): JsonResponse
    {
        $schedule = ShiftSchedule::with(['employee', 'shift', 'createdBy'])->find($id);
        
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule tidak ditemukan'
            ], 404);
        }
        
        return response()->json(['success' => true, 'data' => $schedule]);
    }
    
    public function update(Request $request, $id): JsonResponse
    {
        $schedule = ShiftSchedule::find($id);
        
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule tidak ditemukan'
            ], 404);
        }
        
        $validated = $request->validate([
            'employee_id' => 'sometimes|required|exists:employees,id',
            'shift_id' => 'sometimes|required|exists:shifts,id',
            'schedule_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
        ]);
        
        try {
            $schedule->update($validated);
            $schedule->load(['employee', 'shift', 'createdBy']);
            
            return response()->json([
                'success' => true,
                'message' => 'Schedule berhasil diupdate',
                'data' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update schedule: ' . $e->getMessage()
            ], 400);
        }
    }
    
    public function destroy($id): JsonResponse
    {
        $schedule = ShiftSchedule::find($id);
        
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule tidak ditemukan'
            ], 404);
        }
        
        $schedule->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Schedule berhasil dihapus'
        ]);
    }
    
    // ==================== ADDITIONAL METHODS ====================
    
    public function getByEmployee($employeeId): JsonResponse
    {
        $employee = Employee::find($employeeId);
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee tidak ditemukan'
            ], 404);
        }
        
        $schedules = ShiftSchedule::with(['shift', 'createdBy'])
            ->where('employee_id', $employeeId)
            ->orderBy('schedule_date', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employee,
                'schedules' => $schedules
            ]
        ]);
    }
    
    public function getByDate($date): JsonResponse
    {
        $schedules = ShiftSchedule::with(['employee', 'shift', 'createdBy'])
            ->where('schedule_date', $date)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }
    
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.employee_id' => 'required|exists:employees,id',
            'schedules.*.shift_id' => 'required|exists:shifts,id',
            'schedules.*.schedule_date' => 'required|date',
            'schedules.*.notes' => 'nullable|string',
        ]);
        
        $createdSchedules = [];
        $errors = [];
        
        foreach ($validated['schedules'] as $scheduleData) {
            $scheduleData['created_by'] = Auth::id();
            
            try {
                $schedule = ShiftSchedule::create($scheduleData);
                $schedule->load(['employee', 'shift', 'createdBy']);
                $createdSchedules[] = $schedule;
            } catch (\Exception $e) {
                $errors[] = [
                    'data' => $scheduleData,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($createdSchedules) . ' schedule berhasil dibuat',
            'data' => $createdSchedules,
            'errors' => $errors
        ], count($errors) > 0 ? 207 : 201);
    }
}
