<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadOnly = $request->boolean('unread_only');
        $notifications = NotificationService::getForUser($user->id, $unreadOnly);
        $unreadCount = NotificationService::countUnread($user->id);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => NotificationService::countUnread($request->user()->id)]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $ok = NotificationService::markRead($request->user()->id, $id);
        return $ok
            ? response()->json(['message' => 'Marked as read'])
            : response()->json(['message' => 'Notification not found'], 404);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        NotificationService::markAllRead($request->user()->id);
        return response()->json(['message' => 'All marked as read']);
    }
}
