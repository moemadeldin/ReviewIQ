<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Workspace;

final readonly class GetConnectedRepositories
{
    public function handle(Workspace $workspace, int $page = 1, int $limit = 10): array
    {
        $repos = $workspace->repositories()
            ->latest('repositories.created_at')
            ->simplePaginate($limit, page: $page);

        $items = $repos->getCollection()->map(fn ($repo): array => [
            'id' => $repo->id,
            'full_name' => $repo->full_name,
            'language' => $repo->language,
            'is_active' => $repo->is_active,
            'webhook_id' => $repo->webhook_id,
            'connected_at' => $repo->created_at,
        ]);

        return [
            'repositories' => $items,
            'current_page' => $repos->currentPage(),
            'has_more' => $repos->hasMorePages(),
        ];
    }
}
