<?php

declare(strict_types=1);

namespace App\Contracts;

interface GitHubApi
{
    /**
     * @return array<int, array{id: int, full_name: string, language: string|null}>
     */
    public function getUserRepos(string $token, int $page = 1): array;

    public function registerWebhook(string $token, string $fullName): int;

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void;
}
