<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $icon = null,
        ?string $link = null,
        ?array $data = null,
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'link' => $link,
            'data' => $data,
        ]);
    }

    public static function notifyMultiple(array $userIds, string $type, string $title, ?string $body = null, ?string $icon = null, ?string $link = null, ?array $data = null): void
    {
        $rows = array_map(fn(int $id) => [
            'user_id' => $id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'link' => $link,
            'data' => $data,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $userIds);

        Notification::insert($rows);
    }

    public static function getForUser(int $userId, bool $unreadOnly = false, int $limit = 50)
    {
        $q = Notification::where('user_id', $userId)->orderByDesc('created_at');
        if ($unreadOnly) {
            $q->unread();
        }
        return $q->limit($limit)->get();
    }

    public static function countUnread(int $userId): int
    {
        return Notification::where('user_id', $userId)->unread()->count();
    }

    public static function markRead(int $userId, int $notificationId): bool
    {
        $n = Notification::where('user_id', $userId)->find($notificationId);
        if (!$n) return false;
        $n->markAsRead();
        return true;
    }

    public static function markAllRead(int $userId): void
    {
        Notification::where('user_id', $userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
