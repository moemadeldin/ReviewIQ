<?php

declare(strict_types=1);

use App\Models\Workspace;

it('generates slug on creation from name', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'My Awesome Workspace']);

    expect($workspace->slug)->toBe('my-awesome-workspace');
});

it('regenerates slug on update when name changes', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Original Name']);
    expect($workspace->slug)->toBe('original-name');

    $workspace->update(['name' => 'New Name']);

    expect($workspace->fresh()->slug)->toBe('new-name');
});

it('does not change slug when name stays the same', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Stable Name']);
    $originalSlug = $workspace->slug;

    $workspace->update(['name' => 'Stable Name']);

    expect($workspace->fresh()->slug)->toBe($originalSlug);
});

it('does not overwrite slug when name is empty', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Has Name']);
    $originalSlug = $workspace->slug;

    $workspace->update(['name' => '']);

    expect($workspace->fresh()->slug)->toBe($originalSlug);
});
