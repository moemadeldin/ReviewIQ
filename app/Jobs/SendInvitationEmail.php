<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class SendInvitationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public User $invitedBy,
    ) {}

    public function handle(): void
    {
        $signedUrl = URL::signedRoute('invitations.accept', [
            'token' => $this->invitation->token,
        ]);

        Mail::to($this->invitation->email)->send(new WorkspaceInvitationMail(
            $this->invitation->workspace,
            $this->invitedBy,
            $signedUrl,
        ));
    }
}
