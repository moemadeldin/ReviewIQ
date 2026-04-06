<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class NotificationController
{
    use APIResponder;

    public function index(#[CurrentUser()] User $user, Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 10);

        $notifications = $user->notifications()
            ->latest()
            ->simplePaginate($limit, page: $page);

        $items = $notifications->getCollection()->map(fn ($notification): array => [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIsoString(),
            'created_at' => $notification->created_at->toIsoString(),
        ]);

        return $this->success([
            'notifications' => $items,
            'current_page' => $notifications->currentPage(),
            'has_more' => $notifications->hasMorePages(),
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'ok');
    }

    public function markAsRead(#[CurrentUser()] User $user, string $id): JsonResponse
    {
        $notification = $user->notifications()->find($id);

        if (! $notification) {
            return $this->fail('Notification not found', 404);
        }

        $notification->markAsRead();

        return $this->success(['message' => 'Notification marked as read'], 'ok');
    }

    public function markAllAsRead(#[CurrentUser()] User $user): JsonResponse
    {
        $user->unreadNotifications->markAsRead();

        return $this->success(['message' => 'All notifications marked as read'], 'ok');
    }
}
