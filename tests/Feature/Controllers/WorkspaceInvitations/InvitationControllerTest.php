<?php

declare(strict_types=1);

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user, ['role' => 'owner']);
});

describe('GenerateInvitationController', function (): void {
    it('creates invitation and sends email', function (): void {
        Mail::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('invitations.store'), [
                'email' => 'invitee@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success')
            ->assertJsonPath('message', 'Invitation sent');

        Mail::assertSent(WorkspaceInvitationMail::class);

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);
    });

    it('returns error when user is not admin or owner', function (): void {
        $this->workspace->users()->updateExistingPivot($this->user->id, ['role' => 'member']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('invitations.store'), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Only owners and admins can invite users');
    });

    it('returns error when user is already a workspace member', function (): void {
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);
        $this->workspace->users()->attach($otherUser, ['role' => 'member']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('invitations.store'), [
                'email' => 'existing@example.com',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'User is already a member of this workspace');
    });

    it('returns error when invitation already sent to email', function (): void {
        WorkspaceInvitation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('invitations.store'), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Invitation already sent to this email');
    });

    it('allows re-invitation when previous invitation expired', function (): void {
        WorkspaceInvitation::factory()->expired()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('invitations.store'), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success');
    });
});

describe('AcceptInvitationController', function (): void {
    it('accepts invitation for existing user', function (): void {
        $invitation = WorkspaceInvitation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'existing@example.com',
        ]);

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success')
            ->assertJsonPath('message', 'Invitation accepted');

        $this->assertDatabaseHas('workspace_users', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $existingUser->id,
            'role' => 'member',
        ]);

        $invitation->refresh();
        expect($invitation->accepted_at)->not->toBeNull();
    });

    it('creates new user if email not registered', function (): void {
        $invitation = WorkspaceInvitation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'newuser@example.com',
            'role' => 'admin',
        ]);

        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success');

        $newUser = User::query()->where('email', 'newuser@example.com')->first();
        expect($newUser)->not->toBeNull();

        $this->assertDatabaseHas('workspace_users', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newUser->id,
            'role' => 'admin',
        ]);
    });

    it('returns error for invalid token', function (): void {
        $response = $this->post(route('invitations.accept', ['token' => 'invalid-token']));

        $response->assertStatus(404)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Invalid invitation');
    });

    it('returns error for expired invitation', function (): void {
        $invitation = WorkspaceInvitation::factory()->expired()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]));

        $response->assertStatus(410)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Invitation has expired');
    });

    it('returns error for already accepted invitation', function (): void {
        $invitation = WorkspaceInvitation::factory()->accepted()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]));

        $response->assertStatus(409)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Invitation already used');
    });
});
