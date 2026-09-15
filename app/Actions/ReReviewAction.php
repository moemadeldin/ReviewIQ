<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;

final readonly class ReReviewAction
{
    public function handle(PullRequest $pullRequest): void
    {
        $pullRequest->update(['status' => PullRequestStatus::Pending]);

        dispatch(new ProcessPullRequestReview($pullRequest));
    }
}
