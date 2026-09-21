<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use App\Utilities\Constants;
use Illuminate\Contracts\Pagination\Paginator;

final readonly class GetWorkspaceMembers
{
    /**
     * @return Paginator<int, User&object{pivot: WorkspaceUser}>
     */
    public function handle(Workspace $workspace, int $page = 1, int $limit = Constants::PAGE_LIMIT): Paginator
    {
        return $workspace->users()
            ->latest('workspace_users.created_at')
            ->simplePaginate($limit, page: $page);
    }
}
