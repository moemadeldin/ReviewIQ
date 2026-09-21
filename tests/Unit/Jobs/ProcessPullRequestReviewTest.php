<?php

declare(strict_types=1);

use App\Contracts\AIReviewer;
use App\Contracts\DiffProvider;
use App\Contracts\GitHubAppAuth;
use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DiffLineMapper;
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
        'head_sha' => '51738a50db7241299cae62d372eef1c03886c96d',
    ]);

    $githubApp = $this->mock(GitHubAppAuth::class);
    $githubApp->shouldReceive('getInstallationToken')
        ->once()
        ->andReturn('test-token');

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldReceive('getDiff')
        ->once()
        ->with('test-token', 'owner/repo', 42, '51738a50db7241299cae62d372eef1c03886c96d')
        ->andReturn('diff content');

    $promptBuilder = new PromptBuilder();

    $diffLineMapper = new DiffLineMapper();

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
    $job->handle($diffService, $promptBuilder, $diffLineMapper, $mockAIReviewer, $githubApp);

    $pr->refresh();
    expect($pr->status)->toBe(PullRequestStatus::Reviewed);

    $review = Review::query()->where('pull_request_id', $pr->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->score)->toBe(85)
        ->and($review->summary)->toBe('Good code')
        ->and($review->recommendation)->toBe('approve');
});

it('creates a linked review when a previous review exists', function (): void {
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
        'number' => 43,
        'title' => 'Test PR',
        'head_sha' => '51738a50db7241299cae62d372eef1c03886c96d',
    ]);

    $previous = Review::factory()->create([
        'pull_request_id' => $pr->id,
        'score' => 62,
        'summary' => 'Previous review summary',
        'issues' => [['file' => 'foo.php', 'line' => 1, 'severity' => 'high', 'description' => 'Bug', 'category' => 'correctness', 'suggestion' => 'Fix']],
        'recommendation' => 'request_changes',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $githubApp = $this->mock(GitHubAppAuth::class);
    $githubApp->shouldReceive('getInstallationToken')->once()->andReturn('test-token');

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldReceive('getDiff')
        ->once()
        ->with('test-token', 'owner/repo', 43, '51738a50db7241299cae62d372eef1c03886c96d')
        ->andReturn('diff content');

    $promptBuilder = new PromptBuilder();

    $diffLineMapper = new DiffLineMapper();

    $mockAIReviewer = $this->mock(AIReviewer::class);
    $mockAIReviewer->shouldReceive('review')
        ->once()
        ->withArgs(function (string $systemPrompt, string $userPrompt): bool {
            $containsIncremental = str_contains($systemPrompt, 'INCREMENTAL REVIEW MODE');
            $containsPrevious = str_contains($userPrompt, '<previous_review>')
                && str_contains($userPrompt, 'Previous review summary');

            return $containsIncremental && $containsPrevious;
        })
        ->andReturn([
            'summary' => 'Improved code',
            'score' => 85,
            'score_rationale' => 'Issues fixed',
            'issues' => [],
            'highlights' => ['Good'],
            'recommendation' => 'approve',
        ]);

    $job = new ProcessPullRequestReview($pr);
    $job->handle($diffService, $promptBuilder, $diffLineMapper, $mockAIReviewer, $githubApp);

    $latest = Review::query()
        ->where('pull_request_id', $pr->id)
        ->latest('created_at')
        ->first();

    expect($latest)->not->toBeNull()
        ->and($latest->score)->toBe(85)
        ->and($latest->previous_review_id)->toBe($previous->id)
        ->and($latest->summary)->toBe('Improved code');
});

it('passes no previous review to the prompt on first review', function (): void {
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
        'number' => 44,
        'title' => 'Test PR',
        'head_sha' => '51738a50db7241299cae62d372eef1c03886c96d',
    ]);

    $githubApp = $this->mock(GitHubAppAuth::class);
    $githubApp->shouldReceive('getInstallationToken')->once()->andReturn('test-token');

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldReceive('getDiff')->once()->andReturn('diff content');

    $promptBuilder = new PromptBuilder();

    $diffLineMapper = new DiffLineMapper();

    $mockAIReviewer = $this->mock(AIReviewer::class);
    $mockAIReviewer->shouldReceive('review')
        ->once()
        ->withArgs(function (string $systemPrompt, string $userPrompt): bool {
            return ! str_contains($systemPrompt, 'INCREMENTAL REVIEW MODE')
                && ! str_contains($userPrompt, '<previous_review>');
        })
        ->andReturn([
            'summary' => 'First review',
            'score' => 70,
            'score_rationale' => 'OK',
            'issues' => [],
            'highlights' => [],
            'recommendation' => 'comment',
        ]);

    $job = new ProcessPullRequestReview($pr);
    $job->handle($diffService, $promptBuilder, $diffLineMapper, $mockAIReviewer, $githubApp);

    $latest = Review::query()->where('pull_request_id', $pr->id)->latest('created_at')->first();
    expect($latest)->not->toBeNull()
        ->and($latest->previous_review_id)->toBeNull();
});

it('skips processing when PR is not pending', function (PullRequestStatus $status): void {
    $pr = PullRequest::factory()->create([
        'status' => $status,
    ]);

    $diffService = $this->mock(DiffProvider::class);
    $diffService->shouldNotReceive('getDiff');

    $promptBuilder = new PromptBuilder();

    $diffLineMapper = new DiffLineMapper();

    $mockAIReviewer = $this->mock(AIReviewer::class);
    $mockAIReviewer->shouldNotReceive('review');

    $githubApp = $this->mock(GitHubAppAuth::class);

    $job = new ProcessPullRequestReview($pr);
    $job->handle($diffService, $promptBuilder, $diffLineMapper, $mockAIReviewer, $githubApp);

    $pr->refresh();
    expect($pr->status)->toBe($status);
})->with([
    'reviewing' => PullRequestStatus::Reviewing,
    'reviewed' => PullRequestStatus::Reviewed,
    'failed' => PullRequestStatus::Failed,
]);

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
