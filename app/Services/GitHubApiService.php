<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubApi;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class GitHubApiService implements GitHubApi
{
    /**
     * @return array<int, array{id: int, full_name: string, language: string|null}>
     */
    public function getUserRepos(string $token, int $page = 1, int $perPage = 10): array
    {
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
                    'url' => config('services.github.webhook_url'),
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
}
