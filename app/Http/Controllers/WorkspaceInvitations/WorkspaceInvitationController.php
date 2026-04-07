<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\CreateInvitationAction;
use App\Http\Requests\WorkspaceInvitations\CancelInvitationRequest;
use App\Http\Requests\WorkspaceInvitations\GenerateInvitationRequest;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Queries\GetWorkspaceInvitations;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use RuntimeException;
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
        try {
            $invitation = $action->handle(
                $workspace,
                $user,
                $request->safe()->email,
                $request->safe()->role,
            );
        } catch (RuntimeException $runtimeException) {
            return $this->fail($runtimeException->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->success([
            'invitation' => $invitation,
        ], 'Invitation sent');
    }

    public function destroy(
        CancelInvitationRequest $request,
        Workspace $workspace,
        WorkspaceInvitation $invitation,
    ): JsonResponse {

        if (! $invitation) {
            return $this->fail('Invitation not found', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->workspace_id !== $workspace->id) {
            return $this->fail('Invitation does not belong to this workspace', Response::HTTP_FORBIDDEN);
        }

        if ($invitation->accepted_at !== null) {
            return $this->fail('Cannot cancel accepted invitation', Response::HTTP_CONFLICT);
        }

        $invitation->delete();

        return $this->success(['message' => 'Invitation cancelled'], 'ok');
    }
}
