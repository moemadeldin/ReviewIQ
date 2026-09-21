<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\Review;

it('belongs to a pull request', function (): void {
    $pr = PullRequest::factory()->create();
    $review = Review::factory()->create(['pull_request_id' => $pr->id]);

    expect($review->pullRequest->id)->toBe($pr->id);
});

it('links to a previous review', function (): void {
    $pr = PullRequest::factory()->create();
    $previous = Review::factory()->create(['pull_request_id' => $pr->id]);
    $latest = Review::factory()->create([
        'pull_request_id' => $pr->id,
        'previous_review_id' => $previous->id,
    ]);

    expect($latest->previousReview->id)->toBe($previous->id)
        ->and($previous->nextReview->id)->toBe($latest->id);
});

it('has null previous review by default', function (): void {
    $pr = PullRequest::factory()->create();
    $review = Review::factory()->create(['pull_request_id' => $pr->id]);

    expect($review->previous_review_id)->toBeNull()
        ->and($review->previousReview)->toBeNull();
});

it('pull request review relation returns the latest review', function (): void {
    $pr = PullRequest::factory()->create();
    $first = Review::factory()->create(['pull_request_id' => $pr->id, 'score' => 62]);
    $second = Review::factory()->create([
        'pull_request_id' => $pr->id,
        'score' => 85,
        'previous_review_id' => $first->id,
        'created_at' => now()->addSecond(),
    ]);

    $pr->refresh();

    expect($pr->review->id)->toBe($second->id)
        ->and($pr->review->score)->toBe(85);
});
