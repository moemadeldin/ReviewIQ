<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

it('switches the current workspace', function (): void {
    $user = User::factory()->create();
    $first = Workspace::factory()->withOwner($user)->create(['name' => 'First']);
    $second = Workspace::factory()->withOwner($user)->create(['name' => 'Second']);

    $response = $this->actingAs($user)
        ->from(route('workspaces.show', $first))
        ->post(route('workspaces.switch', $second));

    $response->assertRedirect(route('workspaces.show', $first));
    expect(session('current_workspace_id'))->toBe($second->id);
});

it('does not switch to a workspace the user cannot access', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $foreign = Workspace::factory()->create(['name' => 'Foreign']);

    $response = $this->actingAs($user)
        ->from(route('workspaces.show', $workspace))
        ->post(route('workspaces.switch', $foreign));

    $response->assertRedirect(route('dashboard'));
    expect(session('current_workspace_id'))->toBe($workspace->id);
});
