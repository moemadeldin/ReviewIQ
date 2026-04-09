<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviews:retry')]
#[Description('Retry pending or failed reviews')]
class ReviewRetryCommand extends Command
{
    public function handle(): int
    {
        $prs = PullRequest::query()
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        if ($prs->isEmpty()) {
            $this->info('No pending or failed reviews to retry.');

            return Command::SUCCESS;
        }

        $this->info("Found {$prs->count()} reviews to retry.");

        foreach ($prs as $pr) {
            $this->info("Dispatching review for PR #{$pr->number} ({$pr->id})");
            dispatch(new ProcessPullRequestReview($pr));
        }

        $this->info('All reviews have been queued for retry.');

        return Command::SUCCESS;
    }
}