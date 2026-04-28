<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query();

        // Filter active only
        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $shifts = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Store a newly created shift.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shifts,name',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $shift = Shift::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift created successfully',
            'data' => $shift,
        ], 201);
    }

    /**
     * Display the specified shift.
     */
    public function show(Shift $shift): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $shift->loadCount('employees'),
        ]);
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:shifts,name,' . $shift->id,
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift updated successfully',
            'data' => $shift,
        ]);
    }

    /**
     * Remove the specified shift.
     */
    public function destroy(Shift $shift): JsonResponse
    {
        // Check if shift has employees
        if ($shift->employees()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete shift with assigned employees',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift deleted successfully',
        ]);
    }
}
