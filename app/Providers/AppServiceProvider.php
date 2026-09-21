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
use App\Services\GitHubHttp;
use App\Services\GitHubWebhookService;
use App\Services\OpenRouterReviewService;
use App\Services\PromptBuilder;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitHubHttp::class, fn (): GitHubHttp => new GitHubHttp(
            baseUrl: $this->stringConfig('services.github.base_url'),
        ));

        $this->app->singleton(GitHubApiService::class, fn (): GitHubApiService => new GitHubApiService(
            http: $this->app->make(GitHubHttp::class),
        ));

        $this->app->singleton(GitHubDiffService::class, fn (): GitHubDiffService => new GitHubDiffService(
            http: $this->app->make(GitHubHttp::class),
        ));

        $this->app->singleton(GitHubAppAuthService::class, fn (): GitHubAppAuthService => new GitHubAppAuthService(
            baseUrl: $this->stringConfig('services.github.base_url'),
            http: $this->app->make(GitHubHttp::class),
        ));
        $this->app->bind(GitHubAppAuth::class, GitHubAppAuthService::class);

        $this->app->singleton(OpenRouterReviewService::class, fn (): OpenRouterReviewService => new OpenRouterReviewService(
            client: new Client([
                'timeout' => config('services.openrouter.timeout'),
                'connect_timeout' => config('services.openrouter.connect_timeout', 10),
            ]),
            baseUrl: $this->stringConfig('services.openrouter.base_url'),
            apiKey: $this->stringConfig('services.openrouter.api_key'),
            model: $this->stringConfig('services.openrouter.model'),
            temperature: $this->floatConfig('services.openrouter.temperature', 0.2),
            maxTokens: $this->intConfig('services.openrouter.max_tokens', 12000),
            timeout: $this->intConfig('services.openrouter.timeout', 600),
            connectTimeout: $this->intConfig('services.openrouter.connect_timeout', 10),
            fallbackModels: array_values(array_filter(
                array_map(trim(...), explode(',', $this->stringConfig('services.openrouter.fallback_models'))),
            )),
            jsonObjectFormat: (bool) config('services.openrouter.json_object_format'),
        ));

        $this->app->bind(GitHubApi::class, GitHubApiService::class);
        $this->app->bind(DiffProvider::class, GitHubDiffService::class);
        $this->app->bind(AIReviewer::class, OpenRouterReviewService::class);
        $this->app->bind(WebhookProvider::class, GitHubWebhookService::class);

        $this->app->singleton(PromptBuilder::class, fn (): PromptBuilder => new PromptBuilder(
            maxDiffChars: $this->intConfig('services.prompt.max_diff_chars', 100000),
            ignorePatterns: array_values(array_filter(
                array_map(trim(...), explode(',', $this->stringConfig('services.prompt.ignore_patterns'))),
            )),
            enableIncrementalReviews: (bool) config('services.prompt.enable_incremental_reviews'),
            maxPreviousReviewChars: $this->intConfig('services.prompt.max_previous_review_chars', 4000),
        ));
    }

    private function stringConfig(string $key, string $default = ''): string
    {
        $value = config($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    private function intConfig(string $key, int $default = 0): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function floatConfig(string $key, float $default = 0.0): float
    {
        $value = config($key);

        return is_numeric($value) ? (float) $value : $default;
    }
}
