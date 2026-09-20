<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class GitHubHttp
{
    public function __construct(
        private string $baseUrl,
    ) {}

    /**
     * Create a request for JSON API endpoints (repos, webhooks, reviews, etc.)
     */
    public function json(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => config('services.github.accept_json', 'application/vnd.github+json'),
                'X-GitHub-Api-Version' => config('services.github.api_version', '2022-11-28'),
            ]);
    }

    /**
     * Create a request for diff endpoints
     */
    public function diff(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => config('services.github.accept_diff', 'application/vnd.github.v3.diff'),
                'X-GitHub-Api-Version' => config('services.github.api_version', '2022-11-28'),
            ]);
    }

    /**
     * Create a request for GitHub App installation token endpoints
     */
    public function appAuth(string $jwt): PendingRequest
    {
        return Http::withToken($jwt)
            ->baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => config('services.github.accept_json', 'application/vnd.github+json'),
                'X-GitHub-Api-Version' => config('services.github.api_version', '2022-11-28'),
            ]);
    }
}