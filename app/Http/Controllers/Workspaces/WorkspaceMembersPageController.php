<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetWorkspaceMembers;
use App\Queries\GetWorkspaceTabCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceMembersPageController
{
    public function __construct(
        private GetWorkspaceMembers $getMembers,
        private GetWorkspaceTabCounts $getTabCounts,
    ) {}

    public function __invoke(#[CurrentUser()] User $user, Workspace $workspace): Response
    {
        $userRole = $workspace->roleOf($user);
        $members = $this->getMembers->handle($workspace);

        return Inertia::render('workspaces/members', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'tabCounts' => $this->getTabCounts->handle($workspace),
            'initialMembers' => WorkspaceMemberResource::collection($members->items())->resolve(),
            'membersCurrentPage' => $members->currentPage(),
            'membersHasMore' => $members->hasMorePages(),
        ]);
    }
}
