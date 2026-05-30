<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PullRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class ReviewCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PullRequest $pullRequest,
        public int $score,
        public string $summary,
        public string $reviewUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Review completed',
            'message' => sprintf('#%d %s — Score: %d/100', $this->pullRequest->number, mb_substr($this->summary, 0, 80), $this->score),
            'review_url' => $this->reviewUrl,
            'score' => $this->score,
        ];
    }
}
