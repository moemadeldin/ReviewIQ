<?php

declare(strict_types=1);

use App\Enums\PullRequestStatus;
use App\Exceptions\WebhookException;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\GitHubWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function (): void {
    Config::set('services.github.webhook_secret', 'test-secret');
    Bus::fake();
    Log::spy();
});

function makeWebhookRequest(string $event, string $action, array $overrides = []): Request
{
    $payload = array_merge([
        'action' => $action,
        'repository' => ['id' => 12345],
        'pull_request' => [
            'id' => 999,
            'title' => 'Test PR',
            'number' => 42,
            'user' => ['login' => 'testuser'],
            'diff_url' => 'https://api.github.com/repos/owner/repo/pulls/42',
            'head' => ['sha' => 'abc123'],
        ],
    ], $overrides);

    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    return Request::create(
        uri: '/api/webhooks/github',
        method: 'POST',
        server: [
            'HTTP_X-GitHub-Event' => $event,
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );
}

it('throws exception when signature is missing', function (): void {
    Config::set('services.github.webhook_secret');

    $request = Request::create('/webhook', 'POST', content: '{}');

    $service = new GitHubWebhookService();

    expect(fn () => $service->handle($request))
        ->toThrow(WebhookException::class, 'Missing signature or secret.');
});

it('throws exception on invalid signature', function (): void {
    $body = json_encode(['action' => 'opened']);
    $badSignature = 'sha256=invalidsignature';

    $request = Request::create(
        uri: '/webhook',
        method: 'POST',
        server: [
            'HTTP_X-GitHub-Event' => 'pull_request',
            'HTTP_X-Hub-Signature-256' => $badSignature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );

    $service = new GitHubWebhookService();

    expect(fn () => $service->handle($request))
        ->toThrow(AccessDeniedHttpException::class, 'Invalid signature.');
});

it('returns early for non-pull-request events', function (): void {
    $request = makeWebhookRequest(event: 'push', action: '');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});

it('returns early for unsupported actions', function (): void {
    $request = makeWebhookRequest(event: 'pull_request', action: 'closed');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});

it('returns early when pull_request is missing', function (): void {
    $payload = ['action' => 'opened', 'repository' => ['id' => 12345]];
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $request = Request::create(
        uri: '/webhook',
        method: 'POST',
        server: [
            'HTTP_X-GitHub-Event' => 'pull_request',
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});

it('returns early when repository not found in database', function (): void {
    $request = makeWebhookRequest(event: 'pull_request', action: 'opened');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});

it('creates pull request and dispatches job for opened action', function (): void {
    $workspace = Workspace::factory()->create();
    Repository::factory()->create([
        'github_repo_id' => '12345',
        'workspace_id' => $workspace->id,
    ]);

    $request = makeWebhookRequest(event: 'pull_request', action: 'opened');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertDispatched(ProcessPullRequestReview::class);

    $pr = PullRequest::query()->where('github_pr_id', '999')->first();
    expect($pr)->not->toBeNull()
        ->and($pr->title)->toBe('Test PR')
        ->and($pr->number)->toBe(42)
        ->and($pr->author)->toBe('testuser');
});

it('updates existing pull request and dispatches job for synchronize action', function (): void {
    $workspace = Workspace::factory()->create();
    $repo = Repository::factory()->create([
        'github_repo_id' => '12345',
        'workspace_id' => $workspace->id,
    ]);
    PullRequest::factory()->create([
        'github_pr_id' => '999',
        'repository_id' => $repo->id,
        'title' => 'Old Title',
        'status' => PullRequestStatus::Reviewed,
    ]);

    $request = makeWebhookRequest(event: 'pull_request', action: 'synchronize');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertDispatched(ProcessPullRequestReview::class);

    $pr = PullRequest::query()->where('github_pr_id', '999')->first();
    expect($pr->title)->toBe('Test PR');
});

it('skips dispatch when pr is already reviewing', function (): void {
    $workspace = Workspace::factory()->create();
    $repo = Repository::factory()->create([
        'github_repo_id' => '12345',
        'workspace_id' => $workspace->id,
    ]);
    PullRequest::factory()->create([
        'github_pr_id' => '999',
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Reviewing,
    ]);

    $request = makeWebhookRequest(event: 'pull_request', action: 'opened');

    $service = new GitHubWebhookService();
    $service->handle($request);

    Bus::assertNotDispatched(ProcessPullRequestReview::class);
});
