<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\RepositoryResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Queries\GetWorkspaceTabCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceReposPageController
{
    public function __construct(
        private GetConnectedRepositories $getRepos,
        private GetWorkspaceTabCounts $getTabCounts,
    ) {}

    public function __invoke(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $paginator = $this->getRepos->handle($workspace);

        return Inertia::render('workspaces/repos', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'tabCounts' => $this->getTabCounts->handle($workspace),
            'initialRepos' => RepositoryResource::collection($paginator)->resolve(),
            'reposCurrentPage' => $paginator->currentPage(),
            'reposHasMore' => $paginator->hasMorePages(),
        ]);
    }
}
