<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AIReviewer;
use App\Contracts\DiffProvider;
use App\Contracts\GitHubApi;
use App\Contracts\WebhookProvider;
use App\Services\GitHubApiService;
use App\Services\GitHubDiffService;
use App\Services\GitHubWebhookService;
use App\Services\GroqReviewService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GitHubApi::class, GitHubApiService::class);
        $this->app->bind(WebhookProvider::class, GitHubWebhookService::class);
        $this->app->bind(DiffProvider::class, GitHubDiffService::class);
        $this->app->bind(AIReviewer::class, GroqReviewService::class);
    }
}
