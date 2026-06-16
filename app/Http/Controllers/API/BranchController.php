<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $search = $request->filled('search')
            ? trim((string) $request->input('search'))
            : null;

        $query = Branch::query()->search($search);

        if ($request->boolean('active_only')) {
            $query->active();
        } elseif (($validated['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($validated['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $branches = $query
            ->withCount('employees')
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data cabang berhasil diambil.',
            'data' => $branches,
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());
        $branch->loadCount('employees');

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil dibuat.',
            'data' => $branch,
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $branch->loadCount('employees');

        return response()->json([
            'success' => true,
            'message' => 'Detail cabang berhasil diambil.',
            'data' => $branch,
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());
        $branch->refresh()->loadCount('employees');

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil diperbarui.',
            'data' => $branch,
        ]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang masih digunakan oleh karyawan dan tidak dapat dihapus.',
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil dihapus.',
            'data' => null,
        ]);
    }
}
