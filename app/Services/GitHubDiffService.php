<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DiffProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GitHubDiffService implements DiffProvider
{
    private const int DIFF_CACHE_TTL = 86400; // 24h - SHA-keyed diff is immutable

    public function __construct(private string $baseUrl) {}

    public function getDiff(string $token, string $repoFullName, int $prNumber, string $headSha): string
    {
        $cacheKey = sprintf('github:diff:%s:%d:%s', $repoFullName, $prNumber, $headSha);

        return Cache::remember($cacheKey, self::DIFF_CACHE_TTL, function () use ($token, $repoFullName, $prNumber): string {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => config('services.github.accept_diff'),
                    'X-GitHub-Api-Version' => config('services.github.api_version'),
                ])
                ->get($this->baseUrl.sprintf('/repos/%s/pulls/%d', $repoFullName, $prNumber));

            if ($response->failed()) {
                Log::error(sprintf('Failed to fetch diff for %s #%d', $repoFullName, $prNumber), [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                throw new RuntimeException('Could not fetch PR diff from GitHub.');
            }

            return $response->body();
        });
    }
}
