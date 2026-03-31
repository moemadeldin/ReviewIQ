<?php

declare(strict_types=1);

use App\Actions\CreateWorkspace;
use App\Models\User;
use App\Models\Workspace;

it('creates a workspace with owner', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreateWorkspace::class);

    $workspace = $action->handle($user, 'Acme Inc', 'acme-inc');

    expect($workspace)->toBeInstanceOf(Workspace::class)
        ->and($workspace->name)->toBe('Acme Inc')
        ->and($workspace->slug)->toBe('acme-inc')
        ->and($workspace->owner_id)->toBe($user->id);

    expect($workspace->users)->toHaveCount(1)
        ->and($workspace->users->first()->id)->toBe($user->id)
        ->and($workspace->roleOf($user))->toBe('owner');
});

it('uses database transaction', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreateWorkspace::class);

    $workspace = $action->handle($user, 'Test Workspace', 'test-workspace');

    // If the transaction worked, both workspace and pivot should exist
    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Test Workspace',
    ]);

    $this->assertDatabaseHas('workspace_users', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});
