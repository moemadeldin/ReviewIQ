<?php

declare(strict_types=1);

use App\Services\GitHubDiffService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Config::set('services.github.base_url', 'https://api.github.com');
});

it('fetches diff successfully', function (): void {
    Http::fake([
        'api.github.com/repos/owner/repo/pulls/42' => Http::response('diff --git a/file.php b/file.php', 200),
    ]);

    $service = new GitHubDiffService(baseUrl: config('services.github.base_url'));
    $diff = $service->getDiff('test-token', 'owner/repo', 42);

    expect($diff)->toBe('diff --git a/file.php b/file.php');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Accept', 'application/vnd.github.v3.diff')
        && $request->url() === 'https://api.github.com/repos/owner/repo/pulls/42');
});

it('throws on failed response', function (): void {
    Http::fake([
        'api.github.com/repos/owner/repo/pulls/42' => Http::response('Not Found', 404),
    ]);

    $service = new GitHubDiffService(baseUrl: config('services.github.base_url'));

    expect(fn (): string => $service->getDiff('test-token', 'owner/repo', 42))
        ->toThrow(Exception::class, 'Could not fetch PR diff from GitHub.');
});

it('throws when base url config is missing', function (): void {
    Config::set('services.github.base_url', '');

    $service = new GitHubDiffService(baseUrl: '');

    expect(fn (): string => $service->getDiff('test-token', 'owner/repo', 42))
        ->toThrow(RuntimeException::class);
});
