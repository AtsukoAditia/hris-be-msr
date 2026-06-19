<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'module',
        'action',
        'method',
        'endpoint',
        'route_name',
        'response_status',
        'ip_address',
        'user_agent',
        'latitude',
        'longitude',
        'request_payload',
        'response_payload',
        'description',
        'logged_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'logged_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(ActivityAction $action, string $modelType, int $modelId, array $payload = []): self
    {
        $user = auth()->user();

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'module' => class_basename($modelType),
            'action' => $action->value,
            'method' => request()->method(),
            'endpoint' => request()->path(),
            'route_name' => request()->route()?->getName(),
            'response_status' => 200,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_payload' => $payload,
            'response_payload' => null,
            'description' => $action->value.' '.class_basename($modelType).' #'.$modelId,
            'logged_at' => now(),
        ]);
    }
}
