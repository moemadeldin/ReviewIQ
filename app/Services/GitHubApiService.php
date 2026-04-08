<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubApi;
use Illuminate\Support\Facades\Http;

final readonly class GitHubApiService implements GitHubApi
{
    public function getUserRepos(string $token, int $page = 1, int $perPage = 10): array
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get(config('services.github.base_url').'/user/repos', [
                'page' => $page,
                'per_page' => $perPage,
                'sort' => 'updated',
            ]);

        $response->throw();

        return $response->json();
    }

    public function registerWebhook(string $token, string $fullName): int
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(config('services.github.base_url').'/repos/'.$fullName.'/hooks', [
                'config' => [
                    'url' => config('app.url').'/webhooks/github',
                    'content_type' => 'json',
                ],
                'events' => ['pull_request'],
                'active' => true,
            ]);

        $response->throw();

        return (int) $response->json('id');
    }

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->delete(config('services.github.base_url').'/repos/'.$fullName.'/hooks/'.$webhookId);

        $response->throw();
    }
}
