<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GitHubApi;
use App\Contracts\WebhookProvider;
use App\Services\GitHubApiService;
use App\Services\GitHubWebhookService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GitHubApi::class, GitHubApiService::class);
        $this->app->bind(WebhookProvider::class, GitHubWebhookService::class);
    }
}
