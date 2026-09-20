<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;

final readonly class GetWorkspaceTabCounts
{
    /**
     * @return array{reviews: int, repositories: int, members: int, invitations: int}
     */
    public function handle(Workspace $workspace): array
    {
        return [
            'reviews' => $workspace->pullRequests()->count(),
            'repositories' => $workspace->repositories()->count(),
            'members' => $workspace->users()->count(),
            'invitations' => $workspace->invitations()
                ->whereNull('accepted_at')
                ->count(),
        ];
    }
}
