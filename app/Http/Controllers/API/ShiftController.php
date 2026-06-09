<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
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
            'name' => 'required|string|max:100|unique:shifts,name',
            'code' => 'required|string|max:10|unique:shifts,code',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0',
            'is_overnight' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $shift = Shift::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dibuat.',
            'data' => $shift,
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100|unique:shifts,name,' . $shift->id,
            'code' => 'sometimes|string|max:10|unique:shifts,code,' . $shift->id,
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0',
            'is_overnight' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil diupdate.',
            'data' => $shift,
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if (ShiftSchedule::where('shift_id', $shift->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Shift masih digunakan dalam jadwal, tidak bisa dihapus.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dihapus.',
        ]);
    }
}
