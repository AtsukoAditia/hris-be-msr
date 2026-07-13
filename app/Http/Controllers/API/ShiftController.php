<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query();

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
            });
        }

        $shifts = $query->orderBy('start_time')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data shift berhasil diambil.',
            'data' => $shifts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateShift($request);
        $shift = Shift::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dibuat.',
            'data' => $shift,
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail shift berhasil diambil.',
            'data' => $shift,
        ]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $this->validateShift($request, $shift);
        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil diperbarui.',
            'data' => $shift->refresh(),
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if (ShiftSchedule::where('shift_id', $shift->id)->exists()) {
            $shift->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Shift masih digunakan dalam jadwal, sehingga dinonaktifkan.',
                'data' => $shift->refresh(),
            ]);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dihapus.',
            'data' => null,
        ]);
    }

    private function validateShift(Request $request, ?Shift $shift = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('shifts', 'name')->ignore($shift?->id)->withoutTrashed()],
            'code' => ['required', 'string', 'max:10', Rule::unique('shifts', 'code')->ignore($shift?->id)->withoutTrashed()],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0|max:240',
            'is_overnight' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['name'] = trim($validated['name']);
        $validated['late_tolerance'] = $validated['late_tolerance'] ?? 15;
        $validated['is_overnight'] = $validated['is_overnight'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        if (! $validated['is_overnight'] && $validated['end_time'] <= $validated['start_time']) {
            abort(response()->json([
                'success' => false,
                'message' => 'Jam selesai harus lebih besar dari jam mulai untuk shift non-overnight.',
            ], 422));
        }

        return $validated;
    }
}
