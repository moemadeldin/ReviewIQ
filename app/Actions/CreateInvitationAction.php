<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Roles;
use App\Jobs\SendInvitationEmail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreateInvitationAction
{
    private const int TOKEN_EXPIRY_HOURS = 48;

    public function handle(Workspace $workspace, User $invitedBy, string $email, ?string $role = null): WorkspaceInvitation
    {
        $existingUser = User::query()->where('email', $email)->first();

        throw_if($existingUser && $workspace->users()->where('user_id', $existingUser->id)->exists(), RuntimeException::class, 'User is already a member of this workspace');

        $existingInvitation = WorkspaceInvitation::query()->where('workspace_id', $workspace->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        throw_if($existingInvitation && ! $existingInvitation->isExpired(), RuntimeException::class, 'Invitation already sent to this email');

        $token = Str::random(64);
        $expiresAt = now()->addHours(self::TOKEN_EXPIRY_HOURS);

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'token' => $token,
            'role' => $role ?? Roles::Member->value,
            'expires_at' => $expiresAt,
        ]);

        dispatch(new SendInvitationEmail($invitation, $invitedBy));

        return $invitation;
    }
}
