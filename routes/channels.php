<?php

use App\Models\PullRequest;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('reviews.{prId}', function ($user, $prId) {
    $pr = PullRequest::query()
        ->with('repository.workspace')
        ->find($prId);

    if (! $pr || ! $pr->repository) {
        return false;
    }

    $workspace = $pr->repository->workspace;

    return $workspace->users()->where('users.id', $user->id)->exists();
});
