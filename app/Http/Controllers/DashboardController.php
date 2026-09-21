<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PullRequestResource;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        $recentPullRequests = PullRequest::query()
            ->whereHas('repository', fn (Builder $query) => $query->whereIn('workspace_id', $workspaceIds))
            ->with(['repository', 'review'])
            ->latest('updated_at')
            ->limit(Constants::DASHBOARD_RECENT_PULL_REQUESTS)
            ->get();

        $stats = [
            'workspaces' => $user->workspaces()->count(),
            'repositories' => Repository::query()
                ->whereIn('workspace_id', $workspaceIds)
                ->count(),
            'pullRequests' => PullRequest::query()
                ->whereHas('repository', fn (Builder $query) => $query->whereIn('workspace_id', $workspaceIds))
                ->count(),
            'reviews' => Review::query()
                ->whereHas('pullRequest.repository', fn (Builder $query) => $query->whereIn('workspace_id', $workspaceIds))
                ->count(),
        ];

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'recentPullRequests' => PullRequestResource::collection($recentPullRequests)->resolve(),
        ]);
    }
}
