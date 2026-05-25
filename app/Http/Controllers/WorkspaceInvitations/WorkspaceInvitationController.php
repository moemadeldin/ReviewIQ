<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\CreateInvitationAction;
use App\Http\Requests\WorkspaceInvitations\GenerateInvitationRequest;
use App\Http\Requests\Workspaces\WorkspaceOwnerRequest;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Queries\GetWorkspaceInvitations;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class WorkspaceInvitationController
{
    use APIResponder;

    public function __construct(private GetWorkspaceInvitations $getInvitations) {}

    public function index(
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $invitations = $this->getInvitations->handle($workspace, $page);

        return $this->success([
            'invitations' => WorkspaceInvitationResource::collection($invitations->items()),
            'current_page' => $invitations->currentPage(),
            'has_more' => $invitations->hasMorePages(),
        ], 'ok');
    }

    public function store(
        GenerateInvitationRequest $request,
        #[CurrentUser()] User $user,
        Workspace $workspace,
        CreateInvitationAction $action,
    ): JsonResponse {
        if (! $workspace->isOwnerOrAdmin($user)) {
            return $this->fail('Only owners and admins can invite users', Response::HTTP_FORBIDDEN);
        }

        $invitation = $action->handle($workspace, $user, $request->validated()['email'], $request->validated()['role'] ?? null);

        return $this->success([
            'invitation' => $invitation,
        ], 'Invitation sent');
    }

    public function destroy(
        WorkspaceOwnerRequest $request,
        Workspace $workspace,
        WorkspaceInvitation $invitation,
    ): RedirectResponse {
        if ($invitation->workspace_id !== $workspace->id) {
            return to_route('workspaces.invitations.page', $workspace);
        }

        if ($invitation->accepted_at !== null) {
            return to_route('workspaces.invitations.page', $workspace);
        }

        $invitation->delete();

        return to_route('workspaces.invitations.page', $workspace);
    }
}
