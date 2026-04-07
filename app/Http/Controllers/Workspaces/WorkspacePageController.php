<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\WorkspaceInvitationResource;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Queries\GetWorkspaceInvitations;
use App\Queries\GetWorkspaceMembers;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspacePageController
{
    public function __construct(
        private GetWorkspaceMembers $getMembers,
        private GetWorkspaceInvitations $getInvitations,
        private GetConnectedRepositories $getRepos,
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
        $repos = $this->getRepos->handle($workspace);

        return Inertia::render('workspaces/repos', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'initialRepos' => $repos['repositories'],
            'reposCurrentPage' => $repos['current_page'],
            'reposHasMore' => $repos['has_more'],
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
}
