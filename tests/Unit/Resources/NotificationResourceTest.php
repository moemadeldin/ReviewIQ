<?php

declare(strict_types=1);

use App\Http\Resources\NotificationResource;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;

it('rewrites a stale review url to the canonical pull request page', function (): void {
    $owner = User::factory()->create();
    $workspace = createWorkspaceForUser($owner);

    $repository = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $pr = PullRequest::factory()->create([
        'repository_id' => $repository->id,
        'number' => 7,
        'title' => 'Fix login bug',
    ]);

    $owner->notify(new ReviewCompletedNotification(
        pullRequest: $pr,
        score: 88,
        summary: 'Nice change.',
        reviewUrl: 'https://stale-tunnel.ngrok-free.dev/workspaces/wrong-key/reviews/'.$pr->id,
    ));

    $resolved = NotificationResource::make($owner->notifications()->first())->resolve();

    expect($resolved['data']['review_url'])
        ->toBe('/workspaces/'.$workspace->id.'/reviews/'.$pr->id);
});

it('leaves a review url untouched when the pull request no longer exists', function (): void {
    $owner = User::factory()->create();
    $pr = PullRequest::factory()->create();

    $owner->notify(new ReviewCompletedNotification(
        pullRequest: $pr,
        score: 50,
        summary: 'Old review',
        reviewUrl: '/workspaces/none/reviews/does-not-exist',
    ));

    $resolved = NotificationResource::make($owner->notifications()->first())->resolve();

    expect($resolved['data']['review_url'])->toBe('/workspaces/none/reviews/does-not-exist');
});
