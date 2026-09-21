<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Pagination\Paginator;

final readonly class GetUserNotifications
{
    public function handle(User $user, int $page = 1, int $limit = Constants::PAGE_LIMIT): Paginator
    {
        return $user->notifications()
            ->latest()
            ->simplePaginate($limit, page: $page);
    }
}
