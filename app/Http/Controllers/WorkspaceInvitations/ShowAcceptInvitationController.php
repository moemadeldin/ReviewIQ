<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final readonly class ShowAcceptInvitationController
{
    use APIResponder;

    public function __invoke(string $token): JsonResponse|InertiaResponse
    {
        $invitation = WorkspaceInvitation::findByRawToken($token);

        if (! $invitation instanceof WorkspaceInvitation) {
            return $this->fail('Invalid invitation', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->isExpired()) {
            return $this->fail('Invitation has expired', Response::HTTP_GONE);
        }

        if ($invitation->isAccepted()) {
            return $this->fail('Invitation already used', Response::HTTP_CONFLICT);
        }

        $user = User::query()->whereEmail($invitation->email)->first();

        $roleValue = $invitation->getRawOriginal('role');

        if (! is_string($roleValue)) {
            return $this->fail('Invalid invitation role', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $role = Roles::from($roleValue);

        $workspace = $invitation->workspace;

        if (! $workspace instanceof Workspace) {
            return $this->fail('Workspace not found', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'role' => $role->value,
                'workspace' => [
                    'name' => $workspace->name,
                    'description' => null,
                ],
            ],
            'isExistingUser' => $user !== null,
        ]);
    }
}
