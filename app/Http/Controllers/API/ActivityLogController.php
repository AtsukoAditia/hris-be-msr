<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLog\IndexActivityLogRequest;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * List activity logs (Admin/HR view).
     */
    public function index(IndexActivityLogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        $query = ActivityLog::query()->with(['user:id,name,email,role']);

        // Apply filters
        if (! empty($filters['module'])) {
            $query->where('module', 'ILIKE', "%{$filters['module']}%");
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['user_role'])) {
            $query->where('user_role', $filters['user_role']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('logged_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('logged_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'ILIKE', "%{$search}%")
                    ->orWhere('user_email', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('endpoint', 'ILIKE', "%{$search}%");
            });
        }

        // Default order: newest first
        $query->orderBy('logged_at', 'desc');

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data log aktivitas berhasil diambil.',
            'data' => $logs,
        ]);
    }

    /**
     * Show activity log detail.
     */
    public function show(ActivityLog $activityLog): JsonResponse
    {
        $user = Auth::user();

        // Authorization: only admin or hr can view any log
        if (! $user->isAdmin() && ! $user->isHr()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengakses log ini.',
                'data' => null,
            ], 403);
        }

        $activityLog->load(['user:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Detail log aktivitas berhasil diambil.',
            'data' => $activityLog,
        ]);
    }
}
