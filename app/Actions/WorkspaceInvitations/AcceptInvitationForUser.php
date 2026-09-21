<?php

declare(strict_types=1);

namespace App\Actions\WorkspaceInvitations;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class AcceptInvitationForUser
{
    public function handle(User $user, string $token): Workspace
    {
        return DB::transaction(function () use ($user, $token): Workspace {
            $invitation = WorkspaceInvitation::findByRawToken($token);

            throw_unless($invitation, HttpException::class, Response::HTTP_NOT_FOUND, 'Invalid invitation');

            throw_if($invitation->isExpired(), HttpException::class, Response::HTTP_GONE, 'Invitation has expired');

            throw_if($invitation->isAccepted(), HttpException::class, Response::HTTP_CONFLICT, 'Invitation already used');

            throw_if(
                $invitation->email !== $user->email,
                HttpException::class,
                Response::HTTP_FORBIDDEN,
                'This invitation was sent to a different email address',
            );

            $workspace = $invitation->workspace;

            throw_unless($workspace instanceof Workspace, HttpException::class, Response::HTTP_INTERNAL_SERVER_ERROR, 'Workspace not found');

            $roleValue = $invitation->getRawOriginal('role');

            throw_unless(is_string($roleValue), HttpException::class, Response::HTTP_INTERNAL_SERVER_ERROR, 'Invalid invitation role');

            $workspace->addUser($user, Roles::from($roleValue));

            $invitation->update(['accepted_at' => now()]);

            return $workspace;
        });
    }
}
