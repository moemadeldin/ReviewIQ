<?php

declare(strict_types=1);

use App\Http\Controllers\GitHubController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Workspaces\WorkspacePageController;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel()->ignoring([
    GitHubController::class,
    NotificationController::class,
    WorkspacePageController::class,
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
