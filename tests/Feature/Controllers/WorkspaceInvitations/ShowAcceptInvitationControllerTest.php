<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Response;

it('shows accept invitation page for valid token', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->withToken('valid-token')->create([
        'email' => 'newuser@example.com',
    ]);

    $response = $this->get(route('invitations.accept.page', ['token' => 'valid-token']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/accept')
            ->where('invitation.token', 'valid-token')
            ->where('invitation.email', 'newuser@example.com')
            ->where('invitation.workspace.name', $workspace->name)
            ->where('isExistingUser', false));
});

it('shows accept invitation page for existing user email', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->withToken('existing-token')->create([
        'email' => 'existing@example.com',
    ]);

    $response = $this->get(route('invitations.accept.page', ['token' => 'existing-token']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/accept')
            ->where('invitation.token', 'existing-token')
            ->where('invitation.email', 'existing@example.com')
            ->where('isExistingUser', true));
});

it('returns 404 for invalid token', function (): void {
    $response = $this->get(route('invitations.accept.page', ['token' => 'non-existent-token']));

    $response->assertStatus(Response::HTTP_NOT_FOUND)
        ->assertJsonPath('message', 'Invalid invitation');
});

it('returns 410 for expired invitation', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->withToken('expired-token')->create();

    $response = $this->get(route('invitations.accept.page', ['token' => 'expired-token']));

    $response->assertStatus(Response::HTTP_GONE)
        ->assertJsonPath('message', 'Invitation has expired');
});

it('returns 409 for already accepted invitation', function (): void {
    $workspace = Workspace::factory()->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->accepted()->withToken('accepted-token')->create();

    $response = $this->get(route('invitations.accept.page', ['token' => 'accepted-token']));

    $response->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Invitation already used');
});
