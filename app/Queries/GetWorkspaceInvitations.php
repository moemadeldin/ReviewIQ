<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\Pagination\Paginator;

final readonly class GetWorkspaceInvitations
{
    /**
     * @return Paginator<int, WorkspaceInvitation>
     */
    public function handle(Workspace $workspace, int $page = 1, int $limit = 10): Paginator
    {
        return WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->latest('created_at')
            ->simplePaginate($limit, page: $page);
    }
}
