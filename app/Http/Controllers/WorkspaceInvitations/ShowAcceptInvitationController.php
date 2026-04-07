<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final readonly class ShowAcceptInvitationController
{
    public function __invoke(string $token): Response|View
    {
        $invitation = WorkspaceInvitation::query()->whereToken($token)->first();

        if (! $invitation) {
            return response('Invalid invitation', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->isExpired()) {
            return response('Invitation has expired', Response::HTTP_GONE);
        }

        if ($invitation->isAccepted()) {
            return response('Invitation already used', Response::HTTP_CONFLICT);
        }

        $user = User::query()->whereEmail($invitation->email)->first();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'isExistingUser' => $user !== null,
        ]);
    }
}
