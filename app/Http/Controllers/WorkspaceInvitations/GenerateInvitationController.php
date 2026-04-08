<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\CreateInvitationAction;
use App\Http\Requests\WorkspaceInvitations\GenerateInvitationRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenerateInvitationController
{
    use APIResponder;

    public function __invoke(
        GenerateInvitationRequest $request,
        #[CurrentUser()] User $user,
        CreateInvitationAction $action,
        Workspace $workspace,
    ): JsonResponse {

        if (! $workspace->isOwnerOrAdmin($user)) {
            return $this->fail('Only owners and admins can invite users', Response::HTTP_FORBIDDEN);
        }

        try {
            $email = $request->input('email');
            $role = $request->input('role');
            $invitation = $action->handle($workspace, $user, $email, $role);
        } catch (RuntimeException $runtimeException) {
            return $this->fail($runtimeException->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->success([
            'invitation' => $invitation,
        ], 'Invitation sent', Response::HTTP_CREATED);
    }
}
