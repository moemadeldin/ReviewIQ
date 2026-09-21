<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Utilities\Constants;
use Illuminate\Contracts\Pagination\Paginator;

final readonly class GetWorkspaceInvitations
{
    /**
     * @return Paginator<int, WorkspaceInvitation>
     */
    public function handle(Workspace $workspace, int $page = 1, int $limit = Constants::PAGE_LIMIT): Paginator
    {
        return WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->latest('created_at')
            ->simplePaginate($limit, page: $page);
    }
}
