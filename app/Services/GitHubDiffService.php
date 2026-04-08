<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DiffProvider;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class GitHubDiffService implements DiffProvider
{
    public function getDiff(string $token, string $repoFullName, int $prNumber): string
    {
        $url = config('services.github.base_url').sprintf('/repos/%s/pulls/%d', $repoFullName, $prNumber);

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3.diff',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get($url);

        if ($response->failed()) {
            Log::error(sprintf('Failed to fetch diff for %s #%d', $repoFullName, $prNumber), [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            throw new Exception('Could not fetch PR diff from GitHub.');
        }

        return $response->body();
    }
}
