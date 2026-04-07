<?php

declare(strict_types=1);

namespace App\Actions\WorkspaceInvitations;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Response;
use SensitiveParameter;

final readonly class AcceptInvitationAction
{
    public function handle(string $name, #[SensitiveParameter()] string $password, string $token): User
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

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);
        }

        $invitation->workspace->addUser($user, $invitation->role);

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }
}
