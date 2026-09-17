<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

it('shows accept invitation page for valid token', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->withToken('valid-token')->create([
        'email' => 'newuser@example.com',
    ]);

    $response = $this->get(route('invitations.accept.page', ['token' => 'valid-token']));

    $response->assertOk()
        ->assertViewIs('invitations.accept');
});

it('shows accept invitation page for existing user email', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->withToken('existing-token')->create([
        'email' => 'existing@example.com',
    ]);

    $response = $this->get(route('invitations.accept.page', ['token' => 'existing-token']));

    $response->assertOk()
        ->assertViewIs('invitations.accept');
});

it('returns 404 for invalid token', function (): void {
    $response = $this->get(route('invitations.accept.page', ['token' => 'non-existent-token']));

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Invalid invitation');
});

it('returns 410 for expired invitation', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->withToken('expired-token')->create();

    $response = $this->get(route('invitations.accept.page', ['token' => 'expired-token']));

    $response->assertStatus(410)
        ->assertJsonPath('message', 'Invitation has expired');
});

it('returns 409 for already accepted invitation', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->accepted()->withToken('accepted-token')->create();

    $response = $this->get(route('invitations.accept.page', ['token' => 'accepted-token']));

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Invitation already used');
});
