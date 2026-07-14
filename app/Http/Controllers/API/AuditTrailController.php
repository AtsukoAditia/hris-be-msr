<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    /**
     * Get audit trail for an entity (employee, leave, etc).
     * GET /api/v1/audit-trail/{targetType}/{targetId}
     */
    public function getTrail(string $targetType, int $targetId): JsonResponse
    {
        $trail = AuditTrailService::getTrailWithDiff(
            $this->normalizeTargetType($targetType),
            $targetId,
        );

        return response()->json(['data' => $trail]);
    }

    /**
     * Get all tracked changes (paginated, filterable).
     * GET /api/v1/audit-trail
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->orderByDesc('logged_at');

        if ($request->has('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        if ($request->has('target_id')) {
            $query->where('target_id', $request->input('target_id'));
        }

        if ($request->has('module')) {
            $query->where('module', 'ILIKE', "%{$request->input('module')}%");
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $trail = $query->paginate(min($request->integer('per_page', 20), 100));

        // Add diff lines to each item
        $trail->getCollection()->transform(fn ($log) => [
            'id' => $log->id,
            'user_name' => $log->user_name,
            'user_email' => $log->user_email,
            'user_role' => $log->user_role,
            'module' => $log->module,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'action' => $log->action,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'diff_lines' => $log->getDiffLines(),
            'description' => $log->description,
            'ip_address' => $log->ip_address,
            'logged_at' => $log->logged_at->toISOString(),
        ]);

        return response()->json([
            'data' => $trail->items(),
            'pagination' => [
                'current_page' => $trail->currentPage(),
                'total_pages' => $trail->lastPage(),
                'total_items' => $trail->total(),
                'per_page' => $trail->perPage(),
            ],
        ]);
    }

    private function normalizeTargetType(string $type): string
    {
        return match (strtolower($type)) {
            'employee', 'emp' => 'Employee',
            default => ucfirst($type),
        };
    }
}
