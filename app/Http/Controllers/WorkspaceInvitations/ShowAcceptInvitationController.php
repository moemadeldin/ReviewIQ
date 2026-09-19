<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Models\User;
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

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'workspace' => [
                    'name' => $invitation->workspace->name,
                    'description' => null,
                ],
            ],
            'isExistingUser' => $user !== null,
        ]);
    }
}
