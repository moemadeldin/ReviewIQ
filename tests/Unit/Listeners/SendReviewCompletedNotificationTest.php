<?php

declare(strict_types=1);

use App\Events\ReviewCompleted;
use App\Listeners\SendReviewCompletedNotification;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;

it('stores a relative review url so it works on any host', function (): void {
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

    (new SendReviewCompletedNotification())->handle(new ReviewCompleted(
        prId: $pr->id,
        review: [
            'summary' => 'Solid change.',
            'score' => 87,
        ],
    ));

    $notification = $owner->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(App\Notifications\ReviewCompletedNotification::class)
        ->and($notification->data['review_url'])->toBe('/workspaces/'.$workspace->id.'/reviews/'.$pr->id)
        ->and($notification->data['review_url'])->not->toStartWith('http');
});
