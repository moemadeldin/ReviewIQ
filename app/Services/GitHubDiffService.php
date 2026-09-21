<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DiffProvider;
use App\Utilities\Constants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GitHubDiffService implements DiffProvider
{
    public function __construct(
        private GitHubHttp $http,
    ) {}

    public function getDiff(string $token, string $repoFullName, int $prNumber, string $headSha): string
    {
        $cacheKey = sprintf('github:diff:%s:%d:%s', $repoFullName, $prNumber, $headSha);

        return Cache::remember($cacheKey, Constants::GITHUB_DIFF_CACHE_TTL_SECONDS, function () use ($token, $repoFullName, $prNumber): string {
            $response = $this->http->diff($token)->get(sprintf('/repos/%s/pulls/%d', $repoFullName, $prNumber));

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
