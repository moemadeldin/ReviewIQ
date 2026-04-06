<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;

final readonly class GetWorkspaceMembers
{
    public function handle(Workspace $workspace, int $page = 1): array
    {
        $limit = 10;

        $members = $workspace->users()
            ->latest('workspace_users.created_at')
            ->simplePaginate($limit, page: $page);

        $items = $members->getCollection()->map(fn ($member): array => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->github_avatar,
            'role' => $member->pivot->role,
            'joined_at' => $member->pivot->created_at,
        ]);

        return [
            'members' => $items,
            'current_page' => $members->currentPage(),
            'has_more' => $members->hasMorePages(),
        ];
    }
}
