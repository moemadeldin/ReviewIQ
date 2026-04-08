<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Queries\GetUserNotifications;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class NotificationController
{
    use APIResponder;

    public function __construct(private GetUserNotifications $getNotifications) {}

    public function index(#[CurrentUser()] User $user, Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 10);

        $paginator = $this->getNotifications->handle($user, $page, $limit);

        return $this->success([
            'notifications' => NotificationResource::collection($paginator),
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'ok');
    }

    public function markAsRead(#[CurrentUser()] User $user, string $id): JsonResponse // TODO change $id to be route model binding
    {
        $notification = $user->notifications()->find($id);

        if (! $notification) {
            return $this->fail('Notification not found', Response::HTTP_NOT_FOUND);
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
