<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'shift']);

        // Filter by department if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or NIK
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('nik', 'like', '%' . $request->search . '%');
            });
        }

        $employees = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nik' => 'required|unique:employees,nik',
            'full_name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'shift_id' => 'nullable|exists:shifts,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'join_date' => 'required|date',
        ]);

        $employee = Employee::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $employee->load(['user', 'shift']),
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $employee->load(['user', 'shift']),
        ]);
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'shift_id' => 'nullable|exists:shifts,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee->load(['user', 'shift']),
        ]);
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    /**
     * Get current user's employee profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $employee = $request->user()->employee()->with('shift')->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }
}
