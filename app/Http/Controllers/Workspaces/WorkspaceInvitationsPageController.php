<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\WorkspaceInvitationResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetWorkspaceInvitations;
use App\Queries\GetWorkspaceTabCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceInvitationsPageController
{
    public function __construct(
        private GetWorkspaceInvitations $getInvitations,
        private GetWorkspaceTabCounts $getTabCounts,
    ) {}

    public function __invoke(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $invitations = $this->getInvitations->handle($workspace);

        return Inertia::render('workspaces/invitations', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'tabCounts' => $this->getTabCounts->handle($workspace),
            'initialInvitations' => WorkspaceInvitationResource::collection($invitations->items())->resolve(),
            'invitationsCurrentPage' => $invitations->currentPage(),
            'invitationsHasMore' => $invitations->hasMorePages(),
        ]);
    }
}
