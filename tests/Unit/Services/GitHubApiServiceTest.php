<?php

declare(strict_types=1);

use App\Services\GitHubApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->github = new GitHubApiService();
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

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->url() === 'https://api.github.com/user/repos?per_page=100&sort=updated';
    });
});

it('registers webhook and returns webhook id', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks' => Http::response([
            'id' => 'webhook_123',
        ], 201),
    ]);

    $webhookId = $this->github->registerWebhook('test-token', 'test/repo');

    expect($webhookId)->toBe('webhook_123');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->method() === 'POST'
            && $request->url() === 'https://api.github.com/repos/test/repo/hooks';
    });
});

it('deletes webhook', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks/webhook_123' => Http::response(null, 204),
    ]);

    $this->github->deleteWebhook('test-token', 'test/repo', 'webhook_123');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->method() === 'DELETE'
            && $request->url() === 'https://api.github.com/repos/test/repo/hooks/webhook_123';
    });
});

it('throws on get user repos failure', function (): void {
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            'message' => 'Bad credentials',
        ], 401),
    ]);

    expect(fn () => $this->github->getUserRepos('invalid-token'))
        ->toThrow(Illuminate\Http\Client\RequestException::class);
});

it('throws on register webhook failure', function (): void {
    Http::fake([
        'https://api.github.com/repos/test/repo/hooks' => Http::response([
            'message' => 'Not Found',
        ], 404),
    ]);

    expect(fn () => $this->github->registerWebhook('test-token', 'test/repo'))
        ->toThrow(Illuminate\Http\Client\RequestException::class);
});
