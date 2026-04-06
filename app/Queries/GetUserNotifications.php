<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class GetUserNotifications
{
    public function handle(User $user, int $page = 1, int $limit = 10): array
    {
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

        return [
            'notifications' => $items,
            'current_page' => $notifications->currentPage(),
            'has_more' => $notifications->hasMorePages(),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
