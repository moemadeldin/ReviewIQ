<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

it('returns connected repositories for workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $repo1 = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo1',
        'is_active' => true,
    ]);
    $repo2 = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo2',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('workspaces.repos', ['workspace' => $workspace->slug]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'repositories',
                'current_page',
                'has_more',
            ],
        ]);
});

it('returns empty list when no repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('workspaces.repos', ['workspace' => $workspace->slug]));

    $response->assertOk()
        ->assertJsonPath('data.repositories', [])
        ->assertJsonPath('data.has_more', false);
});
