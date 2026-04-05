<?php

declare(strict_types=1);

namespace App\Contracts;

interface GitHubApi
{
    public function getUserRepos(string $token, int $page = 1): array;

    public function registerWebhook(string $token, string $fullName): int;

    public function deleteWebhook(string $token, string $fullName, string $webhookId): void;
}
