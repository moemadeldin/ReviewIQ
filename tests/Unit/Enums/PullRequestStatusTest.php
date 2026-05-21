<?php

declare(strict_types=1);

use App\Enums\PullRequestStatus;

it('has all expected cases', function (): void {
    expect(PullRequestStatus::cases())->toHaveCount(4);

    expect(PullRequestStatus::Pending->value)->toBe('pending');
    expect(PullRequestStatus::Reviewing->value)->toBe('reviewing');
    expect(PullRequestStatus::Reviewed->value)->toBe('reviewed');
    expect(PullRequestStatus::Failed->value)->toBe('failed');
});

it('returns correct label for each case', function (): void {
    expect(PullRequestStatus::Pending->label())->toBe('Pending');
    expect(PullRequestStatus::Reviewing->label())->toBe('Reviewing');
    expect(PullRequestStatus::Reviewed->label())->toBe('Reviewed');
    expect(PullRequestStatus::Failed->label())->toBe('Failed');
});
