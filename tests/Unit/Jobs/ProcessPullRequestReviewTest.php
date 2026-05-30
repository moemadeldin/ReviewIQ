<?php

declare(strict_types=1);

use App\Contracts\AIReviewer;
use App\Contracts\DiffProvider;
use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Log::spy();
});

it('processes pull request review successfully', function (): void {
    $owner = User::factory()->create(['github_token' => 'test-token']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $repo = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo',
        'language' => 'PHP',
    ]);

    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Pending,
        'number' => 42,
        'title' => 'Test PR',
    ]);

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldReceive('getDiff')
        ->once()
        ->with('test-token', 'owner/repo', 42)
        ->andReturn('diff content');

    $promptBuilder = new PromptBuilder();

    $reviewContent = [
        'summary' => 'Good code',
        'score' => 85,
        'score_rationale' => 'Well written',
        'issues' => [],
        'highlights' => ['Clean code'],
        'recommendation' => 'approve',
    ];

    $mockAIReviewer = $this->mock(AIReviewer::class);
    $mockAIReviewer->shouldReceive('review')
        ->once()
        ->andReturn($reviewContent);

    $job = new ProcessPullRequestReview($pr);
    $job->handle($diffService, $promptBuilder, $mockAIReviewer);

    $pr->refresh();
    expect($pr->status)->toBe(PullRequestStatus::Reviewed);

    $review = Review::query()->where('pull_request_id', $pr->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->score)->toBe(85)
        ->and($review->summary)->toBe('Good code')
        ->and($review->recommendation)->toBe('approve');
});

it('skips processing when PR is already reviewing or reviewed', function (): void {
    $pr = PullRequest::factory()->create([
        'status' => PullRequestStatus::Reviewed,
    ]);

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldNotReceive('getDiff');

    $promptBuilder = new PromptBuilder();

    $mockAIReviewer = $this->mock(AIReviewer::class);
    $mockAIReviewer->shouldNotReceive('review');

    $job = new ProcessPullRequestReview($pr);
    $job->handle($diffService, $promptBuilder, $mockAIReviewer);

    $pr->refresh();
    expect($pr->status)->toBe(PullRequestStatus::Reviewed);
});

it('sets failed status on job failure', function (): void {
    $pr = PullRequest::factory()->create([
        'status' => PullRequestStatus::Pending,
    ]);

    $job = new ProcessPullRequestReview($pr);
    $job->failed(new Exception('Something went wrong'));

    $pr->refresh();
    expect($pr->status)->toBe(PullRequestStatus::Pending);
});

it('returns correct backoff values', function (): void {
    $pr = PullRequest::factory()->create();
    $job = new ProcessPullRequestReview($pr);

    expect($job->backoff())->toBe([30, 120, 300]);
});
