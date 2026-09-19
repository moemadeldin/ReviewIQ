<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->withOwner($this->owner)->create();
});

it('accepts the invitation from the bell and switches to the workspace', function (): void {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->withToken('bell-accept-token')->create([
        'email' => 'invitee@example.com',
        'role' => Roles::Admin->value,
    ]);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept-as-member', ['token' => 'bell-accept-token']));

    $response->assertRedirect(route('dashboard'))
        ->assertSessionHas('current_workspace_id', $this->workspace->id);

    $this->assertDatabaseHas('workspace_users', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $user->id,
        'role' => Roles::Admin->value,
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects an invitation sent to a different email address', function (): void {
    $user = User::factory()->create(['email' => 'someone-else@example.com']);
    $invitation = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->withToken('different-email-token')->create([
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept-as-member', ['token' => 'different-email-token']));

    $response->assertForbidden();

    $this->assertDatabaseMissing('workspace_users', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $user->id,
    ]);

    expect($invitation->fresh()->accepted_at)->toBeNull();
});

it('returns not found for an invalid token', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('invitations.accept-as-member', ['token' => 'invalid-token']));

    $response->assertNotFound();
});

it('returns gone for an expired invitation', function (): void {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    WorkspaceInvitation::factory()->forWorkspace($this->workspace)->expired()->withToken('expired-bell-token')->create([
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept-as-member', ['token' => 'expired-bell-token']));

    $response->assertStatus(410);
});

it('returns conflict for an already accepted invitation', function (): void {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    WorkspaceInvitation::factory()->forWorkspace($this->workspace)->accepted()->withToken('accepted-bell-token')->create([
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept-as-member', ['token' => 'accepted-bell-token']));

    $response->assertStatus(409);
});
