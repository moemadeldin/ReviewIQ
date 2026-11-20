<?php

declare(strict_types=1);

use App\Utilities\Constants;

it('keeps queue retry_after above the review job timeout', function (): void {
    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan(Constants::REVIEW_JOB_TIMEOUT_SECONDS)
        ->and(config('queue.connections.redis.retry_after'))
        ->toBeGreaterThan(Constants::REVIEW_JOB_TIMEOUT_SECONDS);
});
