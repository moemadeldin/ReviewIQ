<?php

declare(strict_types=1);

use App\Http\Controllers\GitHubController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Workspaces\WorkspacePageController;
use App\Http\Requests\Workspaces\WorkspaceOwnerRequest;
use App\Models\Review;
use App\Services\OpenRouterReviewService;

arch()->preset()->php();
arch()->preset()->strict()->ignoring([
    Review::class,
    OpenRouterReviewService::class,
]);
arch()->preset()->laravel()->ignoring([
    GitHubController::class,
    NotificationController::class,
    ReviewController::class,
    WorkspacePageController::class,
    WorkspaceOwnerRequest::class,
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
