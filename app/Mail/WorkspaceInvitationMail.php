<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class WorkspaceInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public User $invitedBy,
        public string $signedUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join ".$this->workspace->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-invitation',
            with: [
                'workspace' => $this->workspace,
                'invitedBy' => $this->invitedBy,
                'signedUrl' => $this->signedUrl,
            ],
        );
    }
}
