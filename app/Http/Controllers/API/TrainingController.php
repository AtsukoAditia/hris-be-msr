<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Training::withCount('activeEnrollments')
            ->orderByDesc('start_date');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $trainings = $query->paginate(min($request->integer('per_page', 20), 100));
        return response()->json([
            'data' => $trainings->items(),
            'pagination' => [
                'current_page' => $trainings->currentPage(),
                'total_pages' => $trainings->lastPage(),
                'total_items' => $trainings->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:50',
            'trainer' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'mode' => 'required|in:online,offline,hybrid',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'max_participants' => 'required|integer|min:1|max:500',
            'cost' => 'nullable|integer|min:0',
            'requirements' => 'nullable|string|max:1000',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'draft';

        $training = Training::create($validated);

        return response()->json(['success' => true, 'message' => 'Pelatihan berhasil dibuat.', 'data' => $training], 201);
    }

    public function show(Training $training): JsonResponse
    {
        $training->loadCount('activeEnrollments');
        $training->available_slots = $training->getAvailableSlots();
        $training->is_open = $training->isOpen();
        return response()->json(['data' => $training]);
    }

    public function update(Request $request, Training $training): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:50',
            'trainer' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'mode' => 'sometimes|in:online,offline,hybrid',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'max_participants' => 'sometimes|integer|min:1|max:500',
            'cost' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:draft,open,closed,ongoing,completed,cancelled',
            'requirements' => 'nullable|string|max:1000',
        ]);

        $training->update($validated);
        return response()->json(['success' => true, 'message' => 'Pelatihan berhasil diperbarui.', 'data' => $training->fresh()]);
    }

    public function destroy(Training $training): JsonResponse
    {
        $training->delete();
        return response()->json(['success' => true, 'message' => 'Pelatihan berhasil dihapus.']);
    }

    public function publish(Training $training): JsonResponse
    {
        if ($training->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya pelatihan draft yang bisa dipublish.'], 422);
        }

        $training->update(['status' => 'open']);
        return response()->json(['success' => true, 'message' => 'Pelatihan berhasil dipublish.', 'data' => $training]);
    }

    public function enroll(Request $request, Training $training): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 422);
        }

        if (!$training->isOpen()) {
            return response()->json(['success' => false, 'message' => 'Pelatihan tidak tersedia atau sudah penuh.'], 422);
        }

        $existing = TrainingEnrollment::where('training_id', $training->id)
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Anda sudah terdaftar di pelatihan ini.'], 422);
        }

        $enrollment = TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'status' => 'registered',
            'enrolled_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Berhasil mendaftar pelatihan.', 'data' => $enrollment], 201);
    }

    public function cancelEnrollment(Training $training, TrainingEnrollment $enrollment): JsonResponse
    {
        if (!in_array($enrollment->status, ['registered', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Pembatalan hanya bisa dilakukan pada status terdaftar/dikonfirmasi.'], 422);
        }

        $enrollment->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Pendaftaran berhasil dibatalkan.']);
    }

    public function myEnrollments(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Profil karyawan tidak ditemukan.'], 422);
        }

        $enrollments = TrainingEnrollment::with('training')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $enrollments->items()]);
    }

    public function getEnrollments(Training $training): JsonResponse
    {
        $enrollments = TrainingEnrollment::with('employee')
            ->where('training_id', $training->id)
            ->get();

        return response()->json(['data' => $enrollments]);
    }
}
