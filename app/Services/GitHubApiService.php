<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubApi;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GitHubApiService implements GitHubApi
{
    private const int REPOS_CACHE_TTL = 300;

    public function __construct(private string $baseUrl) {}

    /**
     * @return array<int, array{id: int, full_name: string, language: string|null}>
     */
    public function getUserRepos(string $token, int $page = 1, int $perPage = 0): array
    {
        $perPage = $perPage > 0 ? $perPage : (int) config('services.github.repos_per_page', 10);

        $cacheKey = sprintf('github:repos:%s:page:%d', hash('sha256', $token), $page);

        return Cache::remember($cacheKey, self::REPOS_CACHE_TTL, function () use ($token, $page, $perPage): array {
            $response = $this->http($token)->get($this->baseUrl.'/user/repos', [
                'page' => $page,
                'per_page' => $perPage,
                'sort' => 'updated',
            ]);

            $response->throw();

            /** @var array<int, array{id: int, full_name: string, language: string|null}> */
            return $response->json();
        });
    }

    public function registerWebhook(string $token, string $fullName): int
    {
        $appUrl = config('app.url');
        throw_unless(is_string($appUrl), RuntimeException::class, 'Invalid app URL configuration');

        $response = $this->http($token)->post($this->baseUrl.'/repos/'.$fullName.'/hooks', [
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

    public function findWebhookId(string $token, string $fullName): ?int
    {
        $appUrl = config('app.url');
        throw_unless(is_string($appUrl), RuntimeException::class, 'Invalid app URL configuration');

        $webhookUrl = config('services.github.webhook_url', $appUrl.'/api/v1/webhooks/github');

        $response = $this->http($token)->get($this->baseUrl.'/repos/'.$fullName.'/hooks', [
            'per_page' => 100,
        ]);

        if ($response->failed()) {
            return null;
        }

        /** @var array<int, array{id: int, config: array{url: string}}> $hooks */
        $hooks = $response->json();

        foreach ($hooks as $hook) {
            if (($hook['config']['url'] ?? '') === $webhookUrl) {
                return $hook['id'];
            }
        }

        return null;
    }

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void
    {
        $this->http($token)
            ->delete($this->baseUrl.'/repos/'.$fullName.'/hooks/'.$webhookId)
            ->throw();
    }

    /**
     * @param  array<int, array{file: string, line: int|null, severity: string, message: string}>  $issues
     */
    public function postReviewComments(
        string $token,
        string $fullName,
        int $prNumber,
        string $commitSha,
        array $issues,
        string $body,
    ): void {
        $comments = $this->buildComments($issues);

        if ($comments === []) {
            return;
        }

        try {
            $this->http($token)
                ->retry(2, 200)
                ->post($this->baseUrl.'/repos/'.$fullName.'/pulls/'.$prNumber.'/reviews', [
                    'commit_id' => $commitSha,
                    'body' => $body,
                    'event' => 'COMMENT',
                    'comments' => $comments,
                ])
                ->throw();
        } catch (RequestException $requestException) {
            throw_if($requestException->response->status() !== Response::HTTP_UNPROCESSABLE_ENTITY, $requestException);

            $this->postCommentsIndividually($token, $fullName, $prNumber, $commitSha, $comments);
        }
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)->withHeaders([
            'Accept' => config('services.github.accept_json'),
            'X-GitHub-Api-Version' => config('services.github.api_version'),
        ]);
    }

    /**
     * @param  array<int, array{file: string, line: int|null, severity: string, message: string}>  $issues
     * @return array<int, array{path: string, line: int, side: string, body: string}>
     */
    private function buildComments(array $issues): array
    {
        $comments = [];

        foreach ($issues as $issue) {
            $line = $issue['line'] ?? null;
            $file = $issue['file'] ?? '';
            if ($line === null) {
                continue;
            }

            if ($file === '') {
                continue;
            }

            $comments[] = [
                'path' => $file,
                'line' => $line,
                'side' => 'RIGHT',
                'body' => sprintf(
                    '**%s**: %s',
                    $issue['severity'] ?? 'medium',
                    $issue['description'] ?? $issue['title'] ?? $issue['message'] ?? '',
                ),
            ];
        }

        return $comments;
    }

    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $comments
     */
    private function postCommentsIndividually(
        string $token,
        string $fullName,
        int $prNumber,
        string $commitSha,
        array $comments,
    ): void {
        Log::warning('Batch review comments rejected (422), posting individually', [
            'repo' => $fullName,
            'pr' => $prNumber,
            'total' => count($comments),
        ]);

        foreach ($comments as $comment) {
            $response = $this->http($token)->post(
                $this->baseUrl.'/repos/'.$fullName.'/pulls/'.$prNumber.'/comments',
                [
                    'commit_id' => $commitSha,
                    'path' => $comment['path'],
                    'line' => $comment['line'],
                    'body' => $comment['body'],
                ],
            );

            if ($response->failed()) {
                Log::warning('Skipping comment with unresolvable path', [
                    'file' => $comment['path'],
                    'line' => $comment['line'],
                    'status' => $response->status(),
                ]);
            }
        }
    }
}
