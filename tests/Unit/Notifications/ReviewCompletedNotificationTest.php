<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;

beforeEach(function (): void {
    $this->pullRequest = PullRequest::factory()->create([
        'number' => 42,
        'title' => 'Fix login bug',
    ]);
});

it('sends via the database channel', function (): void {
    $notification = new ReviewCompletedNotification(
        pullRequest: $this->pullRequest,
        score: 87,
        summary: 'Solid change.',
        reviewUrl: 'http://localhost/reviews/42',
    );

    expect($notification->via(User::factory()->create()))->toBe(['database']);
});

it('returns array data with the review url for the database channel', function (): void {
    $notification = new ReviewCompletedNotification(
        pullRequest: $this->pullRequest,
        score: 87,
        summary: 'Solid change.',
        reviewUrl: 'http://localhost/reviews/42',
    );

    $data = $notification->toArray(User::factory()->create());

    expect($data)->toHaveKeys(['title', 'message', 'review_url', 'score'])
        ->and($data['title'])->toBe('Review completed')
        ->and($data['message'])->toContain('#42')
        ->and($data['review_url'])->toBe('http://localhost/reviews/42')
        ->and($data['score'])->toBe(87);
});
