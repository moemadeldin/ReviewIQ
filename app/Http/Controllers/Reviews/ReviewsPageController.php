<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews;

use App\Http\Resources\PullRequestResource;
use App\Http\Resources\RepositoryResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Queries\GetPullRequestsWithReviews;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ReviewsPageController
{
    public function __construct(
        private GetPullRequestsWithReviews $getPullRequests,
        private GetConnectedRepositories $getRepositories,
    ) {}

    public function __invoke(
        #[CurrentUser()] User $user,
        Workspace $workspace,
        Request $request,
    ): Response {
        $page = (int) $request->query('page', 1);
        $repositoryId = $request->query('repository_id');
        $status = $request->query('status');

        $paginator = $this->getPullRequests->handle(
            $workspace,
            $page,
            $repositoryId,
            $status,
        );

        $repos = $this->getRepositories->handle($workspace, 1, 100);

        return Inertia::render('reviews/index', [
            'workspace' => $workspace,
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
