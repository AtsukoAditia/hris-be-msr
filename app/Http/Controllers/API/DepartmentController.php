<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $query = Department::query()
            ->search(isset($validated['search']) ? trim($validated['search']) : null);

        if ($request->boolean('active_only')) {
            $query->active();
        } elseif (($validated['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($validated['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $departments = $query
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data departemen berhasil diambil.',
            'data' => $departments,
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil dibuat.',
            'data' => $department,
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail departemen berhasil diambil.',
            'data' => $department,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil diperbarui.',
            'data' => $department->refresh(),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil dihapus.',
            'data' => null,
        ]);
    }
}
