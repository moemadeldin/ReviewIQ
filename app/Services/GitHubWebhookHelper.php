<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class GitHubWebhookHelper
{
    /**
     * Get the configured webhook URL.
     */
    public static function webhookUrl(): string
    {
        $appUrl = config('app.url');
        throw_unless(is_string($appUrl), RuntimeException::class, 'Invalid app URL configuration');

        return config('services.github.webhook_url', $appUrl.'/api/v1/webhooks/github');
    }
}
