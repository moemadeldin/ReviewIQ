<?php

declare(strict_types=1);

use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use Illuminate\Support\Facades\Bus;

it('shows info when no pending or failed reviews', function (): void {
    $this->artisan('reviews:retry')
        ->expectsOutput('No pending or failed reviews to retry.')
        ->assertSuccessful();
});

it('dispatches jobs for pending and failed reviews', function (): void {
    Bus::fake();

    PullRequest::factory()->pending()->create();
    PullRequest::factory()->create(['status' => PullRequestStatus::Failed]);

    $this->artisan('reviews:retry')
        ->expectsOutputToContain('Found 2 reviews to retry.')
        ->expectsOutputToContain('All reviews have been queued for retry.')
        ->assertSuccessful();

    Bus::assertDispatchedTimes(ProcessPullRequestReview::class, 2);
});

it('skips reviewed and reviewing prs', function (): void {
    Bus::fake();

    PullRequest::factory()->reviewed()->create();
    PullRequest::factory()->create(['status' => PullRequestStatus::Reviewing]);

    $this->artisan('reviews:retry')
        ->expectsOutput('No pending or failed reviews to retry.')
        ->assertSuccessful();

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});
