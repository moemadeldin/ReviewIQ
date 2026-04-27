<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;

final class SendInvitationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public User $invitedBy,
    ) {}

    public function handle(): void
    {
        $workspace = $this->invitation->workspace;
        throw_unless($workspace instanceof Workspace, RuntimeException::class, 'Workspace not found');

        $signedUrl = URL::signedRoute('invitations.accept.page', [
            'token' => $this->invitation->token,
        ]);

        Mail::to($this->invitation->email)->send(new WorkspaceInvitationMail(
            $workspace,
            $this->invitedBy,
            $signedUrl,
        ));
    }
}
