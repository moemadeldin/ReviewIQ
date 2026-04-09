<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\PullRequestDetailResource;
use App\Http\Resources\PullRequestResource;
use App\Http\Resources\RepositoryResource;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Queries\GetPullRequestsWithReviews;
use App\Queries\GetWorkspaceInvitations;
use App\Queries\GetWorkspaceMembers;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspacePageController
{
    public function __construct(
        private GetWorkspaceMembers $getMembers,
        private GetWorkspaceInvitations $getInvitations,
        private GetConnectedRepositories $getRepos,
        private GetPullRequestsWithReviews $getPullRequests,
    ) {}

    public function members(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $members = $this->getMembers->handle($workspace);

        return Inertia::render('workspaces/members', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'initialMembers' => WorkspaceMemberResource::collection($members->items())->resolve(),
            'membersCurrentPage' => $members->currentPage(),
            'membersHasMore' => $members->hasMorePages(),
        ]);
    }

    public function repos(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $paginator = $this->getRepos->handle($workspace);

        return Inertia::render('workspaces/repos', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'initialRepos' => RepositoryResource::collection($paginator)->resolve(),
            'reposCurrentPage' => $paginator->currentPage(),
            'reposHasMore' => $paginator->hasMorePages(),
        ]);
    }

    public function invitations(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $invitations = $this->getInvitations->handle($workspace);

        return Inertia::render('workspaces/invitations', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'initialInvitations' => WorkspaceInvitationResource::collection($invitations->items())->resolve(),
            'invitationsCurrentPage' => $invitations->currentPage(),
            'invitationsHasMore' => $invitations->hasMorePages(),
        ]);
    }

    public function reviews(#[CurrentUser()] User $user, Workspace $workspace, Request $request): Response
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

    public function review(#[CurrentUser()] User $user, Workspace $workspace, PullRequest $pullRequest): Response
    {
        $userRole = $workspace->roleOf($user);
        $pullRequest->load(['repository', 'review']);

        return Inertia::render('reviews/show', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'pullRequest' => new PullRequestDetailResource($pullRequest)->resolve(),
        ]);
    }
}
