<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\DeleteWorkspaceMember;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetWorkspaceMembers;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final readonly class WorkspaceMemberController
{
    use APIResponder;

    public function __construct(private GetWorkspaceMembers $getWorkspaceMembers) {}

    public function index(
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $data = $this->getWorkspaceMembers->handle($workspace, $page);

        return $this->success([
            'members' => WorkspaceMemberResource::collection($data->items())->resolve(),
            'current_page' => $data->currentPage(),
            'has_more' => $data->hasMorePages(),
        ], 'ok');
    }

    public function destroy(
        #[CurrentUser()] User $user,
        Workspace $workspace,
        User $member,
        DeleteWorkspaceMember $action,
    ): RedirectResponse {
        $action->handle($workspace, $user, $member);

        return to_route('workspaces.members.page', $workspace);
    }
}
