<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrailService
{
    private static string $trackedModule = '';
    private static ?Model $trackedModel = null;
    private static array $oldSnapshot = [];

    /**
     * Capture model state before mutation. Call BEFORE saving.
     */
    public static function track(string $module, Model $model): void
    {
        self::$trackedModule = $module;
        self::$trackedModel = $model;
        self::$oldSnapshot = $model->getAttributes();
    }

    /**
     * Record the change. Call AFTER save/update.
     */
    public static function record(?string $action = 'update', ?string $description = null): ?ActivityLog
    {
        if (!self::$trackedModel) return null;

        $model = self::$trackedModel;
        $oldValues = self::$oldSnapshot;
        $newValues = $model->getAttributes();

        // Only track actual changed fields
        $changedOld = [];
        $changedNew = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        foreach ($allKeys as $key) {
            if (in_array($key, ['updated_at', 'created_at'])) continue;
            if (($oldValues[$key] ?? null) !== ($newValues[$key] ?? null)) {
                $changedOld[$key] = $oldValues[$key];
                $changedNew[$key] = $newValues[$key];
            }
        }

        if (empty($changedOld) && empty($changedNew)) {
            self::reset();
            return null;
        }

        $user = request()->user();
        $moduleName = self::$trackedModule;

        $log = ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'module' => $moduleName,
            'target_type' => class_basename($model),
            'target_id' => $model->getKey(),
            'action' => $action,
            'method' => request()->method(),
            'endpoint' => request()->path(),
            'route_name' => request()->route()?->getName(),
            'response_status' => 200,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $changedOld,
            'new_values' => $changedNew,
            'description' => $description ?? self::autoDescription($moduleName, $action, $model),
            'logged_at' => now(),
        ]);

        self::reset();
        return $log;
    }

    /**
     * Record a creation event.
     */
    public static function recordCreation(Model $model, ?string $description = null): ?ActivityLog
    {
        self::track(self::guessModule($model), $model);
        return self::record('create', $description);
    }

    /**
     * Record an explicit change (old state provided manually).
     */
    public static function recordChange(
        string $module,
        Model $model,
        array $oldValues,
        array $newValues,
        ?string $action = 'update',
        ?string $description = null,
    ): ?ActivityLog {
        $user = request()->user();

        $changedOld = [];
        $changedNew = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        foreach ($allKeys as $key) {
            if (($oldValues[$key] ?? null) !== ($newValues[$key] ?? null)) {
                $changedOld[$key] = $oldValues[$key];
                $changedNew[$key] = $newValues[$key];
            }
        }

        if (empty($changedOld) && empty($changedNew)) return null;

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'module' => $module,
            'target_type' => class_basename($model),
            'target_id' => $model->getKey(),
            'action' => $action,
            'method' => request()->method(),
            'endpoint' => request()->path(),
            'route_name' => request()->route()?->getName(),
            'response_status' => 200,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $changedOld,
            'new_values' => $changedNew,
            'description' => $description,
            'logged_at' => now(),
        ]);
    }

    /**
     * Get audit trail for a specific entity.
     */
    public static function getTrail(string $targetType, int $targetId): \Illuminate\Support\Collection
    {
        return ActivityLog::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderByDesc('logged_at')
            ->get();
    }

    /**
     * Get entity change history with diff lines.
     */
    public static function getTrailWithDiff(string $targetType, int $targetId): \Illuminate\Support\Collection
    {
        return self::getTrail($targetType, $targetId)->map(function ($log) {
            $log->diff_lines = $log->getDiffLines();
            return $log;
        });
    }

    private static function guessModule(Model $model): string
    {
        return strtolower(class_basename($model));
    }

    private static function autoDescription(string $module, ?string $action, Model $model): string
    {
        $moduleName = ucfirst(str_replace('_', ' ', $module));
        $modelId = $model->getKey();
        return match ($action) {
            'create' => "Membuat {$moduleName} #{$modelId}",
            'delete' => "Menghapus {$moduleName} #{$modelId}",
            default => "Memperbarui {$moduleName} #{$modelId}",
        };
    }

    private static function reset(): void
    {
        self::$trackedModule = '';
        self::$trackedModel = null;
        self::$oldSnapshot = [];
    }
}
