<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class GetConnectedRepositories
{
    public function handle(Workspace $workspace, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return $workspace->repositories()
            ->latest('repositories.created_at')
            ->simplePaginate($limit, page: $page);
    }
}
