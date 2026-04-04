<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Workspace;

test('belongs to workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->for($workspace)->create();

    expect($repository->workspace->id)->toBe($workspace->id);
});

test('has repositories relationship', function (): void {
    $workspace = Workspace::factory()->create();
    Repository::factory()->count(3)->for($workspace)->create();

    expect($workspace->repositories)->toHaveCount(3);
});

test('has correct casts', function (): void {
    $repository = Repository::factory()->create();

    expect($repository->github_repo_id)->toBeString()
        ->and($repository->is_active)->toBeBool();
});

test('language can be null', function (): void {
    $repository = Repository::factory()->create(['language' => null]);
    expect($repository->language)->toBeNull();

    $repository2 = Repository::factory()->create(['language' => 'PHP']);
    expect($repository2->language)->toBe('PHP');
});

test('can toggle active status', function (): void {
    $repository = Repository::factory()->create(['is_active' => true]);

    expect($repository->is_active)->toBeTrue();

    $repository->update(['is_active' => false]);

    expect($repository->fresh()->is_active)->toBeFalse();
});

test('can store custom rules', function (): void {
    $rules = '{"require_approval": true, "min_reviewers": 2}';
    $repository = Repository::factory()->create(['custom_rules' => $rules]);

    expect($repository->custom_rules)->toBe($rules);
});
