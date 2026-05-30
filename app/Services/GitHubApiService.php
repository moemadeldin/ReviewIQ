<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class GitHubApiService implements GitHubApi
{
    /**
     * @return array<int, array{id: int, full_name: string, language: string|null}>
     */
    public function getUserRepos(string $token, int $page = 1, int $perPage = 10): array
    {
        $cacheKey = sprintf('github:repos:%s:page:%d', hash('sha256', $token), $page);

        return Cache::remember($cacheKey, 300, function () use ($token, $page, $perPage): array {
            $baseUrl = config('services.github.base_url');
            throw_unless(is_string($baseUrl), RuntimeException::class, 'Invalid GitHub base URL configuration');

            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->get($baseUrl.'/user/repos', [
                    'page' => $page,
                    'per_page' => $perPage,
                    'sort' => 'updated',
                ]);

            $response->throw();

            /** @var array<int, array{id: int, full_name: string, language: string|null}> $data */
            $data = $response->json();

            return $data;
        });
    }

    public function registerWebhook(string $token, string $fullName): int
    {
        $baseUrl = config('services.github.base_url');
        $appUrl = config('app.url');

        throw_if(! is_string($baseUrl) || ! is_string($appUrl), RuntimeException::class, 'Invalid GitHub configuration');

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post($baseUrl.'/repos/'.$fullName.'/hooks', [
                'config' => [
                    'url' => config('services.github.webhook_url', $appUrl.'/api/v1/webhooks/github'),
                    'content_type' => 'json',
                ],
                'events' => ['pull_request'],
                'active' => true,
            ]);

        $response->throw();

        /** @var array{id: int}|null $json */
        $json = $response->json();

        throw_unless(isset($json['id']), RuntimeException::class, 'Failed to register webhook');

        return $json['id'];
    }

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void
    {
        $baseUrl = config('services.github.base_url');
        throw_unless(is_string($baseUrl), RuntimeException::class, 'Invalid GitHub base URL configuration');

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->delete($baseUrl.'/repos/'.$fullName.'/hooks/'.$webhookId);

        $response->throw();
    }

    /**
     * @param  array<int, array{file: string, line: int|null, severity: string, message: string}>  $issues
     */
    public function postReviewComments(string $token, string $fullName, int $prNumber, string $commitSha, array $issues, string $body): void
    {
        $baseUrl = config('services.github.base_url');
        throw_unless(is_string($baseUrl), RuntimeException::class, 'Invalid GitHub base URL configuration');

        $comments = [];

        foreach ($issues as $issue) {
            if (! isset($issue['line'])) {
                continue;
            }

            if ($issue['line'] === null) {
                continue;
            }

            if (! isset($issue['file'])) {
                continue;
            }
            if ($issue['file'] === '') {
                continue;
            }

            $comments[] = [
                'path' => $issue['file'],
                'line' => $issue['line'],
                'side' => 'RIGHT',
                'body' => sprintf('**%s**: %s', $issue['severity'] ?? 'medium', $issue['description'] ?? $issue['title'] ?? $issue['message'] ?? ''),
            ];
        }

        if ($comments === []) {
            return;
        }

        try {
            $response = Http::retry(2, 200)->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->post($baseUrl.'/repos/'.$fullName.'/pulls/'.$prNumber.'/reviews', [
                    'commit_id' => $commitSha,
                    'body' => $body,
                    'event' => 'COMMENT',
                    'comments' => $comments,
                ]);

            $response->throw();
        } catch (RequestException $e) {
            if ($e->response->status() !== 422) {
                throw $e;
            }

            Log::warning('Batch review comments rejected (422), posting individually', [
                'repo' => $fullName,
                'pr' => $prNumber,
                'total' => count($comments),
            ]);

            foreach ($comments as $single) {
                $resp = Http::withToken($token)
                    ->withHeaders([
                        'Accept' => 'application/vnd.github+json',
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ])
                    ->post($baseUrl.'/repos/'.$fullName.'/pulls/'.$prNumber.'/comments', [
                        'commit_id' => $commitSha,
                        'path' => $single['path'],
                        'line' => $single['line'],
                        'body' => $single['body'],
                    ]);

                if ($resp->failed()) {
                    Log::warning('Skipping comment with unresolvable path', [
                        'file' => $single['path'],
                        'line' => $single['line'],
                        'status' => $resp->status(),
                    ]);
                }
            }
        }
    }
}
