<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;

it('returns workspace members', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $member1 = User::factory()->create(['name' => 'Alice']);
    $member2 = User::factory()->create(['name' => 'Bob']);

    $workspace->addUser($member1, Roles::Admin);
    $workspace->addUser($member2, Roles::Member);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('workspaces.members', ['workspace' => $workspace->slug]));

    $response->assertOk();
});

it('returns empty members list', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('workspaces.members', ['workspace' => $workspace->slug]));

    $response->assertOk();
});
