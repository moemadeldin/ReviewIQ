<?php

declare(strict_types=1);

use App\Models\WorkspaceInvitation;

it('returns expired when expires_at is null on model instance', function (): void {
    $invitation = WorkspaceInvitation::factory()->create();
    $invitation->expires_at = null;

    expect($invitation->isExpired())->toBeTrue();
});

it('returns expired when expires_at is in the past', function (): void {
    $invitation = WorkspaceInvitation::factory()->create([
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->isExpired())->toBeTrue();
});
