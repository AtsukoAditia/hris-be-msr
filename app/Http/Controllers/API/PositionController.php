<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $search = $request->filled('search')
            ? trim((string) $request->input('search'))
            : null;

        $query = Position::query()
            ->with('department')
            ->search($search);

        if (! empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        } elseif (($validated['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($validated['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $positions = $query
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data jabatan berhasil diambil.',
            'data' => $positions,
        ]);
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());
        $position->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil dibuat.',
            'data' => $position,
        ], 201);
    }

    public function show(Position $position): JsonResponse
    {
        $position->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Detail jabatan berhasil diambil.',
            'data' => $position,
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());
        $position->refresh()->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil diperbarui.',
            'data' => $position,
        ]);
    }

    public function destroy(Position $position): JsonResponse
    {
        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil dihapus.',
            'data' => null,
        ]);
    }
}
