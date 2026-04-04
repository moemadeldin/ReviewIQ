<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final readonly class GitHubApiService
{
    private const BASE_URL = 'https://api.github.com';

    public function getUserRepos(string $token, int $perPage = 100): array
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get(self::BASE_URL.'/user/repos', [
                'per_page' => $perPage,
                'sort' => 'updated',
            ]);

        $response->throw();

        return $response->json();
    }

    public function registerWebhook(string $token, string $fullName): string
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(self::BASE_URL.'/repos/'.$fullName.'/hooks', [
                'config' => [
                    'url' => config('app.url').'/webhooks/github',
                    'content_type' => 'json',
                ],
                'events' => ['pull_request'],
                'active' => true,
            ]);

        $response->throw();

        return (string) $response->json('id');
    }

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->delete(self::BASE_URL.'/repos/'.$fullName.'/hooks/'.$webhookId);

        $response->throw();
    }
}
