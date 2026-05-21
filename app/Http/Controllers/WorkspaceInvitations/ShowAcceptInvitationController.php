<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Traits\APIResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class ShowAcceptInvitationController
{
    use APIResponder;

    public function __invoke(string $token): JsonResponse|View
    {
        $invitation = WorkspaceInvitation::query()->whereToken($token)->first();

        if (! $invitation) {
            return $this->fail('Invalid invitation', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->isExpired()) {
            return $this->fail('Invitation has expired', Response::HTTP_GONE);
        }

        if ($invitation->isAccepted()) {
            return $this->fail('Invitation already used', Response::HTTP_CONFLICT);
        }

        $user = User::query()->whereEmail($invitation->email)->first();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'isExistingUser' => $user !== null,
        ]);
    }
}
