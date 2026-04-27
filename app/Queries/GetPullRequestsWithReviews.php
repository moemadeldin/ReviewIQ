<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PullRequest;
use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GetPullRequestsWithReviews
{
    /**
     * @return LengthAwarePaginator<int, PullRequest>
     */
    public function handle(
        Workspace $workspace,
        int $page = 1,
        ?string $repositoryId = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = PullRequest::query()
            ->whereHas('repository', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with(['review', 'repository']);

        if ($repositoryId) {
            $query->where('repository_id', $repositoryId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(perPage: 15, page: $page);
    }
}
