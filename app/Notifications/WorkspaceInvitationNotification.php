<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Workspace $workspace,
        public User $invitedBy,
        public string $acceptUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage()
            ->subject('You have been invited to join a workspace')
            ->greeting('Hello!')
            ->line(sprintf("You've been invited by %s to join the workspace: %s", $this->invitedBy->name, $this->workspace->name))
            ->action('Accept Invitation', $this->acceptUrl)
            ->line('This invitation will expire in 48 hours.')
            ->line('If you did not expect this invitation, no further action is required.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Workspace Invitation',
            'message' => sprintf("You've been invited by %s to join %s", $this->invitedBy->name, $this->workspace->name),
            'workspace_id' => $this->workspace->id,
            'workspace_slug' => $this->workspace->slug,
            'workspace_name' => $this->workspace->name,
            'invited_by' => $this->invitedBy->name,
            'accept_url' => $this->acceptUrl,
        ];
    }
}
