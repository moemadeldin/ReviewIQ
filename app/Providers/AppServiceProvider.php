<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AIReviewer;
use App\Contracts\DiffProvider;
use App\Contracts\GitHubApi;
use App\Contracts\GitHubAppAuth;
use App\Contracts\WebhookProvider;
use App\Services\GitHubApiService;
use App\Services\GitHubAppAuth as GitHubAppAuthService;
use App\Services\GitHubDiffService;
use App\Services\GitHubWebhookService;
use App\Services\OpenRouterReviewService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitHubApiService::class, fn (): GitHubApiService => new GitHubApiService(
            baseUrl: config('services.github.base_url'),
        ));

        $this->app->singleton(GitHubDiffService::class, fn (): GitHubDiffService => new GitHubDiffService(
            baseUrl: config('services.github.base_url'),
        ));
        $this->app->singleton(GitHubAppAuthService::class, fn (): GitHubAppAuthService => new GitHubAppAuthService());
        $this->app->bind(GitHubAppAuth::class, GitHubAppAuthService::class);

        $this->app->singleton(OpenRouterReviewService::class, fn (): OpenRouterReviewService => new OpenRouterReviewService(
            client: new Client(['timeout' => config('services.openrouter.timeout')]),
            baseUrl: config('services.openrouter.base_url'),
            apiKey: config('services.openrouter.api_key'),
            model: config('services.openrouter.model'),
            temperature: (float) config('services.openrouter.temperature'),
            maxTokens: (int) config('services.openrouter.max_tokens'),
            timeout: (int) config('services.openrouter.timeout'),
        ));

        $this->app->bind(GitHubApi::class, GitHubApiService::class);
        $this->app->bind(DiffProvider::class, GitHubDiffService::class);
        $this->app->bind(AIReviewer::class, OpenRouterReviewService::class);
        $this->app->bind(WebhookProvider::class, GitHubWebhookService::class);
    }
}
