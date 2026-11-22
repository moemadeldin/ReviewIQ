<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\PullRequestResource;
use App\Http\Resources\RepositoryResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Queries\GetPullRequestsWithReviews;
use App\Queries\GetWorkspaceTabCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceReviewsPageController
{
    public function __construct(
        private GetPullRequestsWithReviews $getPullRequests,
        private GetConnectedRepositories $getRepos,
        private GetWorkspaceTabCounts $getTabCounts,
    ) {}

    public function __invoke(#[CurrentUser()] User $user, Workspace $workspace, Request $request): Response
    {
        $userRole = $workspace->roleOf($user);
        $page = (int) $request->query('page', 1);
        $repositoryId = $request->query('repository_id');
        $status = $request->query('status');

        $paginator = $this->getPullRequests->handle(
            $workspace,
            $page,
            $repositoryId,
            $status,
        );

        $repos = $this->getRepos->handle($workspace, 1, 100);

        return Inertia::render('reviews/index', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'tabCounts' => $this->getTabCounts->handle($workspace),
            'initialPullRequests' => PullRequestResource::collection($paginator)->resolve(),
            'currentPage' => $paginator->currentPage(),
            'hasMore' => $paginator->hasMorePages(),
            'repositories' => RepositoryResource::collection($repos)->resolve(),
            'filters' => [
                'repository_id' => $repositoryId,
                'status' => $status ?? 'all',
            ],
        ]);
    }
}
