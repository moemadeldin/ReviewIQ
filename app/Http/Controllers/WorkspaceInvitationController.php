<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvitationAction;
use App\Http\Requests\CancelInvitationRequest;
use App\Http\Requests\GenerateInvitationRequest;
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
        #[CurrentUser()] User $user,
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $data = $this->getInvitations->handle($workspace, $page);

        return $this->success($data, 'ok');
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
        #[CurrentUser()] User $user,
        Workspace $workspace,
        WorkspaceInvitation $invitation,
    ): JsonResponse {
        $invitationModel = WorkspaceInvitation::query()->find($invitation);

        if (! $invitationModel) {
            return $this->fail('Invitation not found', Response::HTTP_NOT_FOUND);
        }

        if ($invitationModel->workspace_id !== $workspace->id) {
            return $this->fail('Invitation does not belong to this workspace', Response::HTTP_FORBIDDEN);
        }

        if ($invitationModel->accepted_at !== null) {
            return $this->fail('Cannot cancel accepted invitation', Response::HTTP_CONFLICT);
        }

        $invitationModel->delete();

        return $this->success(['message' => 'Invitation cancelled'], 'ok');
    }
}
