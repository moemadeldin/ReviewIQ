<?php

declare(strict_types=1);

use App\Contracts\GitHubAppAuth;
use App\Jobs\PostReviewComments;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GitHubApiService;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Log::spy();

    $owner = User::factory()->create(['github_token' => 'test-token']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $repo = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo',
        'language' => 'PHP',
    ]);

    $this->pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'number' => 23,
        'head_sha' => '51738a50db7241299cae62d372eef1c03886c96d',
    ]);

    $this->github = app()->make(GitHubApiService::class);
});

function resolveToken(): GitHubAppAuth
{
    $githubApp = mock(GitHubAppAuth::class);
    $githubApp->shouldReceive('getInstallationToken')->once()->andReturn('test-token');

    return $githubApp;
}

it('posts a body-only review when the review has no issues', function (): void {
    Review::factory()->create([
        'pull_request_id' => $this->pr->id,
        'score' => 85,
        'summary' => 'Great work',
        'issues' => [],
        'recommendation' => 'approve',
    ]);

    Http::fake([
        'https://api.github.com/repos/owner/repo/pulls/23/reviews' => Http::response([], Response::HTTP_OK),
    ]);

    $job = new PostReviewComments($this->pr);
    $job->handle($this->github, resolveToken());

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/repos/owner/repo/pulls/23/reviews'
        && $request['body'] === '## ReviewIQ Review — Score: 85/'.Constants::AI_SCORE_MAX."\n\n".Constants::REVIEW_NO_ISSUES_MESSAGE
        && $request['event'] === 'COMMENT'
        && $request['commit_id'] === '51738a50db7241299cae62d372eef1c03886c96d'
        && ! isset($request['comments']));
});

it('posts inline comments with the summary body when issues exist', function (): void {
    Review::factory()->create([
        'pull_request_id' => $this->pr->id,
        'score' => 65,
        'summary' => 'Almost there',
        'issues' => [
            ['file' => 'app/Foo.php', 'line' => 12, 'severity' => 'high', 'message' => 'Bug'],
        ],
        'recommendation' => 'request_changes',
    ]);

    Http::fake([
        'https://api.github.com/repos/owner/repo/pulls/23/reviews' => Http::response([], Response::HTTP_OK),
    ]);

    $job = new PostReviewComments($this->pr);
    $job->handle($this->github, resolveToken());

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/repos/owner/repo/pulls/23/reviews'
        && $request['body'] === '## ReviewIQ Review — Score: 65/'.Constants::AI_SCORE_MAX."\n\nAlmost there"
        && count($request['comments']) === 1
        && $request['comments'][0]['path'] === 'app/Foo.php'
        && $request['comments'][0]['line'] === 12);
});

it('logs and returns when no review exists', function (): void {
    Http::fake();

    $job = new PostReviewComments($this->pr);
    $job->handle($this->github, mock(GitHubAppAuth::class));

    Http::assertNothingSent();

    Log::shouldHaveReceived('info')
        ->once()
        ->with('No review to post comments for PR #23');
});
