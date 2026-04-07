<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;

final readonly class GetWorkspaceMembers
{
    public function handle(Workspace $workspace, int $page = 1, int $limit = 10)
    {
        return $workspace->users()
            ->latest('workspace_users.created_at')
            ->simplePaginate($limit, page: $page);
    }
}