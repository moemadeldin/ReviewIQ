<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'email_verified_at',
            'two_factor_confirmed_at',
            'github_id',
            'github_avatar',
            'github_token',
            'created_at',
            'updated_at',
        ]);
});

test('has owned workspaces', function (): void {
    $user = User::factory()->create();
    Workspace::factory(2)->create(['owner_id' => $user->id]);

    expect($user->ownedWorkspaces)->toHaveCount(2);
});
