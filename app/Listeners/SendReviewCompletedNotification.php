<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewCompleted;
use App\Models\PullRequest;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class SendReviewCompletedNotification
{
    public function handle(ReviewCompleted $event): void
    {
        $pr = PullRequest::query()->with('repository.workspace.owner')->find($event->prId);

        if (! $pr instanceof PullRequest) {
            Log::warning('ReviewCompleted listener: PullRequest not found', ['prId' => $event->prId]);

            return;
        }

        $owner = $pr->repository?->workspace?->owner;

        if (! $owner instanceof User) {
            Log::warning('ReviewCompleted listener: workspace owner not found', ['prId' => $event->prId]);

            return;
        }

        $workspace = $pr->repository->workspace;
        throw_unless(is_string($workspace->slug), RuntimeException::class, 'Workspace slug is missing');

        $reviewUrl = route('reviews.show', [$workspace, $pr]);
        $score = (int) ($event->review['score'] ?? 0);
        $summary = (string) ($event->review['summary'] ?? '');

        $owner->notify(new ReviewCompletedNotification(
            pullRequest: $pr,
            score: $score,
            summary: $summary,
            reviewUrl: $reviewUrl,
        ));
    }
}
