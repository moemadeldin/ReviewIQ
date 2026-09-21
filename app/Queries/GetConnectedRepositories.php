<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Repository;
use App\Models\Workspace;
use App\Utilities\Constants;
use Illuminate\Contracts\Pagination\Paginator;

final readonly class GetConnectedRepositories
{
    /**
     * @return Paginator<int, Repository>
     */
    public function handle(Workspace $workspace, int $page = 1, int $limit = Constants::PAGE_LIMIT): Paginator
    {
        return $workspace->repositories()
            ->latest('repositories.created_at')
            ->simplePaginate($limit, page: $page);
    }
}
