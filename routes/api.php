<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/webhooks/github', GitHubWebhookController::class)->name('api.v1.webhooks.github');
});
