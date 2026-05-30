<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviews:retry')]
#[Description('Retry pending or failed reviews')]
final class ReviewRetryCommand extends Command
{
    public function handle(): int
    {
        $prs = PullRequest::query()
            ->whereNot('status', PullRequestStatus::Reviewed)
            ->get();

        if ($prs->isEmpty()) {
            $this->info('No pending or failed reviews to retry.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d reviews to retry.', $prs->count()));

        foreach ($prs as $pr) {
            $this->info(sprintf('Dispatching review for PR #%s (%s)', $pr->number, $pr->id));
            dispatch(new ProcessPullRequestReview($pr));
        }

        $this->info('All reviews have been queued for retry.');

        return Command::SUCCESS;
    }
}
