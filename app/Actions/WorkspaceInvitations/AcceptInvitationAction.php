<?php

declare(strict_types=1);

namespace App\Actions\WorkspaceInvitations;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Response;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class AcceptInvitationAction
{
    public function handle(string $name, #[SensitiveParameter()] string $password, string $token): User
    {
        $invitation = WorkspaceInvitation::query()->whereToken($token)->first();

        throw_unless($invitation, HttpException::class, Response::HTTP_NOT_FOUND, 'Invalid invitation');

        throw_if($invitation->isExpired(), HttpException::class, Response::HTTP_GONE, 'Invitation has expired');

        throw_if($invitation->isAccepted(), HttpException::class, Response::HTTP_CONFLICT, 'Invitation already used');

        $user = User::query()->whereEmail($invitation->email)->first();

        $workspace = $invitation->workspace;
        throw_unless($workspace instanceof Workspace, HttpException::class, Response::HTTP_INTERNAL_SERVER_ERROR, 'Workspace not found');

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            return $this->addUserToWorkspace($workspace, $invitation, $user);
        }

        return $this->addUserToWorkspace($workspace, $invitation, $user);
    }

    private function addUserToWorkspace(Workspace $workspace, WorkspaceInvitation $invitation, User $user): User
    {
        $workspace->addUser($user, $invitation->role);

        $invitation->update(['accepted_at' => now()]);

        $updatedUser = $user->fresh('workspaces');

        throw_unless($updatedUser instanceof User, HttpException::class, Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to load user');

        return $updatedUser;
    }
}
