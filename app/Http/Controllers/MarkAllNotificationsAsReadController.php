<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MarkAllNotificationsAsReadController
{
    use APIResponder;

    public function __invoke(#[CurrentUser()] User $user): JsonResponse
    {
        $user->unreadNotifications->markAsRead();

        return $this->success(['message' => 'All notifications marked as read'], 'ok');
    }
}
