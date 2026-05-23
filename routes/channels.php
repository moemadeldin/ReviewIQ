<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id): bool => (int) $user->id === $id);

Broadcast::channel('reviews.{prId}', function (User $user, string $prId): bool {
    /** @var PullRequest|null $pr */
    $pr = PullRequest::query()
        ->with('repository.workspace')
        ->find($prId);

    if (! $pr || ! $pr->repository) {
        return false;
    }

    /** @var Workspace|null $workspace */
    $workspace = $pr->repository->workspace;

    if (! $workspace) {
        return false;
    }

    return $workspace->users()->where('users.id', $user->id)->exists();
});
