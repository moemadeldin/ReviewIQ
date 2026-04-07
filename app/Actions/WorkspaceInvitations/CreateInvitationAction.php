<?php

declare(strict_types=1);

namespace App\Actions\WorkspaceInvitations;

use App\Enums\Roles;
use App\Jobs\SendInvitationEmail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final readonly class CreateInvitationAction
{
    private const int TOKEN_EXPIRY_HOURS = 48;

    private const int TOKEN_NUMBER_OF_CHARS = 64;

    public function handle(Workspace $workspace, User $invitedBy, string $email, string|Roles $role): WorkspaceInvitation
    {
        $existingUser = User::query()->whereEmail($email)->first();

        throw_if($existingUser && $workspace->users()->where('user_id', $existingUser->id)->exists(), RuntimeException::class, 'User is already a member of this workspace');

        $existingInvitation = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereEmail($email)
            ->whereNull('accepted_at')
            ->first();

        throw_if($existingInvitation && ! $existingInvitation->isExpired(), RuntimeException::class, 'Invitation already sent to this email');

        $token = Str::random(self::TOKEN_NUMBER_OF_CHARS);

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'token' => $token,
            'role' => $role ?? Roles::Member->value,
            'expires_at' => now()->addHours(self::TOKEN_EXPIRY_HOURS),
            'created_at' => now(),
        ]);

        try {
            $acceptUrl = route('invitations.accept.page', ['token' => $token], absolute: false);
        } catch (RouteNotFoundException) {
            $acceptUrl = url('/invitations/'.$token.'/accept');
        }

        dispatch(new SendInvitationEmail($invitation, $invitedBy));

        if ($existingUser instanceof User) {
            $existingUser->notify(new WorkspaceInvitationNotification(
                $workspace,
                $invitedBy,
                $acceptUrl,
            ));
        }

        return $invitation;
    }
}
