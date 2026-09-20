<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->withOwner($this->owner)->create();
    $this->member = User::factory()->create(['name' => 'Bob']);
    $this->workspace->addUser($this->member, Roles::Member);
});

it('removes a member and redirects to the members page', function (): void {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->delete(route('workspaces.delete.member', [
            'workspace' => $this->workspace->id,
            'member' => $this->member->id,
        ]))
        ->assertRedirect(route('workspaces.members.page', $this->workspace));

    expect($this->workspace->users()->where('users.id', $this->member->id)->exists())->toBeFalse();

    $this->getJson(route('workspaces.members', ['workspace' => $this->workspace->id]))
        ->assertOk()
        ->assertJsonMissing(['id' => $this->member->id]);
});

it('does not allow a non-owner to remove a member', function (): void {
    $actor = User::factory()->create();
    $this->workspace->addUser($actor, Roles::Member);

    $this->actingAs($actor)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->delete(route('workspaces.delete.member', [
            'workspace' => $this->workspace->id,
            'member' => $this->member->id,
        ]))
        ->assertForbidden();

    expect($this->workspace->users()->where('users.id', $this->member->id)->exists())->toBeTrue();
});
