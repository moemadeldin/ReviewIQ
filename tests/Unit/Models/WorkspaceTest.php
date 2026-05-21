<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

test('has owner relationship', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    expect($workspace->owner->id)->toBe($user->id);
});

test('has users relationship', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    expect($workspace->fresh()->users)->toHaveCount(1)
        ->and($workspace->users->first()->id)->toBe($user->id);
});

test('add user to workspace', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($owner)->create();

    $workspace->addUser($member, Roles::Member);

    expect($workspace->fresh()->users)->toHaveCount(2)
        ->and($workspace->roleOf($member))->toBe(Roles::Member);
});

test('get role of user', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($owner)->create();

    expect($workspace->roleOf($owner))->toBe(Roles::Owner);
});

test('get null role for non-member', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($workspace->roleOf($user))->toBeNull();
});

test('slug from name', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Acme Inc']);
    expect($workspace->slug)->toBe('acme-inc');
});

test('user can access owned workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    expect($user->canAccessWorkspace($workspace))->toBeTrue();
});

test('user cannot access other workspace', function (): void {
    $user = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create();

    expect($user->canAccessWorkspace($otherWorkspace))->toBeFalse();
});

test('checks if user is owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($owner)->create();

    expect($workspace->isOwner($owner))->toBeTrue();
    expect($workspace->isOwner($other))->toBeFalse();
});

test('returns null role when pivot role is empty', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    DB::table('workspace_users')->insert([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($workspace->roleOf($user))->toBeNull();
});
