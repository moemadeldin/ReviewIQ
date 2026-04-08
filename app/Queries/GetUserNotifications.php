<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class GetUserNotifications
{
    public function handle(User $user, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return $user->notifications()
            ->latest()
            ->simplePaginate($limit, page: $page);
    }
}
