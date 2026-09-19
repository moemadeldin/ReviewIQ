<?php

declare(strict_types=1);

use App\Services\GitHubApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->github = new GitHubApiService(baseUrl: config('services.github.base_url'));
});

it('gets user repositories', function (): void {
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 12345,
                'full_name' => 'test/repo1',
                'language' => 'PHP',
            ],
            [
                'id' => 67890,
                'full_name' => 'test/repo2',
                'language' => 'JavaScript',
            ],
        ], 200),
    ]);

    $repos = $this->github->getUserRepos('test-token');

    expect($repos)->toHaveCount(2)
        ->and($repos[0]['full_name'])->toBe('test/repo1')
        ->and($repos[1]['full_name'])->toBe('test/repo2');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->url() === 'https://api.github.com/user/repos?page=1&per_page=10&sort=updated');
});

it('registers webhook and returns webhook id', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks' => Http::response([
            'id' => 123,
        ], 201),
    ]);

    $webhookId = $this->github->registerWebhook('test-token', 'test/repo');

    expect($webhookId)->toBe(123);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/repos/test/repo/hooks');
});

it('deletes webhook', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks/webhook_123' => Http::response(null, 204),
    ]);

    $this->github->deleteWebhook('test-token', 'test/repo', 'webhook_123');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->method() === 'DELETE'
        && $request->url() === 'https://api.github.com/repos/test/repo/hooks/webhook_123');
});

it('throws on get user repos failure', function (): void {
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            'message' => 'Bad credentials',
        ], 401),
    ]);

    expect(fn () => $this->github->getUserRepos('invalid-token'))
        ->toThrow(RequestException::class);
});

it('throws on register webhook failure', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks' => Http::response([
            'message' => 'Not Found',
        ], 404),
    ]);

    expect(fn () => $this->github->registerWebhook('test-token', 'test/repo'))
        ->toThrow(RequestException::class);
});

it('posts review body only when no line numbers are present', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/pulls/42/reviews' => Http::response([], 200),
    ]);

    $posted = $this->github->postReviewComments(
        token: 'test-token',
        fullName: 'test/repo',
        prNumber: 42,
        commitSha: 'abc123',
        issues: [
            ['file' => 'app/Foo.php', 'line' => null, 'severity' => 'high', 'message' => 'Bug'],
            ['file' => 'app/Bar.php', 'line' => null, 'severity' => 'low', 'message' => 'Nit'],
        ],
        body: '## ReviewIQ Review — Score: 65/100',
    );

    expect($posted)->toBe(0);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/repos/test/repo/pulls/42/reviews'
        && $request['event'] === 'COMMENT'
        && $request['body'] === '## ReviewIQ Review — Score: 65/100'
        && ! isset($request['comments']));
});

it('posts review with inline comments for issues with line numbers', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/pulls/42/reviews' => Http::response([], 200),
    ]);

    $posted = $this->github->postReviewComments(
        token: 'test-token',
        fullName: 'test/repo',
        prNumber: 42,
        commitSha: 'abc123',
        issues: [
            ['file' => 'app/Foo.php', 'line' => 12, 'severity' => 'high', 'message' => 'Bug'],
            ['file' => 'app/Bar.php', 'line' => 4, 'severity' => 'low', 'message' => 'Nit'],
        ],
        body: '## ReviewIQ Review — Score: 90/100',
    );

    expect($posted)->toBe(2);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/repos/test/repo/pulls/42/reviews'
        && count($request['comments']) === 2
        && $request['comments'][0]['path'] === 'app/Foo.php'
        && $request['comments'][0]['line'] === 12);
});
