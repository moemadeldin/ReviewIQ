<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewCompleted;
use App\Models\PullRequest;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use Illuminate\Support\Facades\Log;

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

        $reviewUrl = url()->route('reviews.show', [$workspace, $pr], absolute: false);
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
