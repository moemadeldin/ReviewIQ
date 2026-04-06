<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

final readonly class GetWorkspaceInvitations
{
    public function handle(Workspace $workspace, int $page = 1): array
    {
        $limit = 10;

        $invitations = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            // ->latest('created_at')
            ->simplePaginate($limit, page: $page);

        $items = $invitations->getCollection()->map(fn ($invitation): array => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at,
            // 'created_at' => $invitation->created_at,
        ]);

        return [
            'invitations' => $items,
            'current_page' => $invitations->currentPage(),
            'has_more' => $invitations->hasMorePages(),
        ];
    }
}
