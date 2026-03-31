<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

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

    $workspace->addUser($member, 'member');

    expect($workspace->fresh()->users)->toHaveCount(2)
        ->and($workspace->roleOf($member))->toBe('member');
});

test('get role of user', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($owner)->create();

    expect($workspace->roleOf($owner))->toBe('owner');
});

test('get null role for non-member', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($workspace->roleOf($user))->toBeNull();
});

test('slug from name', function (): void {
    expect(Workspace::slugFromName('Acme Inc'))->toBe('acme-inc')
        ->and(Workspace::slugFromName('My Workspace!'))->toBe('my-workspace')
        ->and(Workspace::slugFromName('UPPER Case'))->toBe('upper-case');
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
