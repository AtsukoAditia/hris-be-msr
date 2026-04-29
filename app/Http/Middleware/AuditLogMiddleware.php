<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();
            $route = $request->route();
            $payload = $this->sanitizePayload($request->all());
            $responsePayload = $this->extractResponsePayload($response);
            $coordinates = $this->extractCoordinates($request);

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'user_role' => $user?->role,
                'module' => $this->resolveModule($request),
                'action' => $this->resolveAction($request),
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'route_name' => $route?->getName(),
                'response_status' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'request_payload' => $payload,
                'response_payload' => $responsePayload,
                'description' => $this->buildDescription($request, $user?->name),
                'logged_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[FILTERED]';
            }
        }

        return $payload;
    }

    private function extractResponsePayload(Response $response): ?array
    {
        $content = $response->getContent();

        if (!is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return [
                'raw' => mb_substr($content, 0, 2000),
            ];
        }

        return $this->limitResponsePayload($decoded);
    }

    private function limitResponsePayload(array $payload): array
    {
        $json = json_encode($payload);

        if ($json !== false && strlen($json) <= 4000) {
            return $payload;
        }

        return [
            'message' => $payload['message'] ?? null,
            'success' => $payload['success'] ?? null,
            'data_preview' => '[TRUNCATED]',
        ];
    }

    private function extractCoordinates(Request $request): array
    {
        return [
            'latitude' => $request->input('latitude') ?? $request->input('lat'),
            'longitude' => $request->input('longitude') ?? $request->input('lng'),
        ];
    }

    private function resolveModule(Request $request): ?string
    {
        $segments = $request->segments();

        if (count($segments) >= 3) {
            return $segments[2];
        }

        return $segments[0] ?? null;
    }

    private function resolveAction(Request $request): string
    {
        $method = strtoupper($request->method());

        return match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method),
        };
    }

    private function buildDescription(Request $request, ?string $userName): string
    {
        $actor = $userName ?: 'Guest';
        $method = strtoupper($request->method());
        $endpoint = $request->path();

        return sprintf('%s performed %s on %s', $actor, $method, $endpoint);
    }
}
