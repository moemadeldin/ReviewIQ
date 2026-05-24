<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

it('shows accept invitation page for valid token', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($workspace)->create([
        'email' => 'newuser@example.com',
    ]);

    $response = $this->fromRoute('home')
        ->get(route('invitations.accept.page', ['token' => $invitation->token]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('invitations/accept', [
            'invitation' => fn ($i) => $i['token'] === $invitation->token,
            'isExistingUser' => false,
        ]));
});

it('shows accept invitation page for existing user email', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $workspace = Workspace::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($workspace)->create([
        'email' => 'existing@example.com',
    ]);

    $response = $this->fromRoute('home')
        ->get(route('invitations.accept.page', ['token' => $invitation->token]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('invitations/accept', [
            'invitation' => fn ($i) => $i['token'] === $invitation->token,
            'isExistingUser' => true,
        ]));
});

it('returns 404 for invalid token', function (): void {
    $response = $this->get(route('invitations.accept.page', ['token' => 'non-existent-token']));

    $response->assertStatus(404);
});

it('returns 410 for expired invitation', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->create();

    $response = $this->get(route('invitations.accept.page', ['token' => $invitation->token]));

    $response->assertStatus(410);
});

it('returns 409 for already accepted invitation', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($workspace)->accepted()->create();

    $response = $this->get(route('invitations.accept.page', ['token' => $invitation->token]));

    $response->assertStatus(409);
});
