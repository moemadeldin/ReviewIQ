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

    $service = app()->make(\App\Services\GitHubDiffService::class);
    $diff = $service->getDiff('test-token', 'owner/repo', 42, 'abc123');

    expect($diff)->toBe('diff --git a/file.php b/file.php');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Accept', config('services.github.accept_diff'))
        && $request->url() === 'https://api.github.com/repos/owner/repo/pulls/42');
});

it('throws on failed response', function (): void {
    Http::fake([
        'api.github.com/repos/owner/repo/pulls/42' => Http::response('Not Found', 404),
    ]);

    $service = app()->make(\App\Services\GitHubDiffService::class);

    expect(fn (): string => $service->getDiff('test-token', 'owner/repo', 42, 'abc123'))
        ->toThrow(Exception::class, 'Could not fetch PR diff from GitHub.');
});

it('throws when base url config is missing', function (): void {
    Config::set('services.github.base_url', '');

    // Need to create a new instance since container has singleton with valid URL
    $http = new \App\Services\GitHubHttp('');
    $service = new \App\Services\GitHubDiffService('', $http);

    expect(fn (): string => $service->getDiff('test-token', 'owner/repo', 42, 'abc123'))
        ->toThrow(RuntimeException::class);
});
