<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\Paginator;

final readonly class GetConnectedRepositories
{
    /**
     * @return Paginator<int, Repository>
     */
    public function handle(Workspace $workspace, int $page = 1, int $limit = 10): Paginator
    {
        return $workspace->repositories()
            ->latest('repositories.created_at')
            ->simplePaginate($limit, page: $page);
    }
}
