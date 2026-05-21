<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\Review;

it('belongs to a pull request', function (): void {
    $pr = PullRequest::factory()->create();
    $review = Review::factory()->create(['pull_request_id' => $pr->id]);

    expect($review->pullRequest->id)->toBe($pr->id);
});
