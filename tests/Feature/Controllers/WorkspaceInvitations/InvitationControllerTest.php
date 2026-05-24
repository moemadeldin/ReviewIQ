<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withOwner($this->user)->create();
});

describe('GenerateInvitationController (workspaces.invitations.store)', function (): void {
    it('creates invitation and sends email', function (): void {
        Mail::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('workspaces.invitations.store', ['workspace' => $this->workspace->slug]), [
                'email' => 'invitee@example.com',
                'role' => Roles::Member->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success')
            ->assertJsonPath('message', 'Invitation sent');

        Mail::assertQueued(WorkspaceInvitationMail::class);

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
            'role' => Roles::Member->value,
        ]);
    });

    it('returns error when user is not admin or owner', function (): void {
        $this->user->workspaces()->updateExistingPivot($this->workspace->id, ['role' => Roles::Member->value]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('workspaces.invitations.store', ['workspace' => $this->workspace->slug]), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'Failed')
            ->assertJsonPath('message', 'Only owners and admins can invite users');
    });

    it('returns error when user is already a workspace member', function (): void {
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);
        $this->workspace->users()->attach($otherUser, ['role' => Roles::Member->value]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('workspaces.invitations.store', ['workspace' => $this->workspace->slug]), [
                'email' => 'existing@example.com',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'User is already a member of this workspace');
    });

    it('returns error when invitation already sent to email', function (): void {
        WorkspaceInvitation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('workspaces.invitations.store', ['workspace' => $this->workspace->slug]), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Invitation already sent to this email');
    });

    it('allows re-invitation when previous invitation expired', function (): void {
        WorkspaceInvitation::factory()->expired()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'invitee@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('workspaces.invitations.store', ['workspace' => $this->workspace->slug]), [
                'email' => 'invitee@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success');
    });
});

describe('WorkspaceInvitationController', function (): void {
    it('lists invitations for workspace', function (): void {
        $invitation1 = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->create();
        $invitation2 = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->getJson(route('workspaces.invitations', ['workspace' => $this->workspace->slug]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'invitations',
                    'current_page',
                    'has_more',
                ],
            ])
            ->assertJsonCount(2, 'data.invitations');
    });

    it('returns empty list when no invitations', function (): void {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->getJson(route('workspaces.invitations', ['workspace' => $this->workspace->slug]));

        $response->assertOk()
            ->assertJsonPath('data.invitations', [])
            ->assertJsonPath('data.has_more', false);
    });

    it('deletes invitation successfully', function (): void {
        $invitation = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->deleteJson(route('workspaces.invitations.destroy', [
                'workspace' => $this->workspace->slug,
                'invitation' => $invitation->id,
            ]), [
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Invitation cancelled');

        expect(WorkspaceInvitation::query()->find($invitation->id))->toBeNull();
    });

    it('returns 403 when invitation belongs to different workspace', function (): void {
        $otherUser = User::factory()->create();
        $otherWorkspace = Workspace::factory()->withOwner($otherUser)->create();
        $invitation = WorkspaceInvitation::factory()->forWorkspace($otherWorkspace)->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->deleteJson(route('workspaces.invitations.destroy', [
                'workspace' => $this->workspace->slug,
                'invitation' => $invitation->id,
            ]), [
                'password' => 'password',
            ]);

        $response->assertStatus(403);
    });

    it('returns 409 when trying to delete accepted invitation', function (): void {
        $invitation = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->accepted()->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->deleteJson(route('workspaces.invitations.destroy', [
                'workspace' => $this->workspace->slug,
                'invitation' => $invitation->id,
            ]), [
                'password' => 'password',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Cannot cancel accepted invitation');
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

        $response->assertRedirect();

        $this->assertDatabaseHas('workspace_users', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $existingUser->id,
            'role' => Roles::Member->value,
        ]);

        $invitation->refresh();
        expect($invitation->accepted_at)->not->toBeNull();
    });

    it('creates new user if email not registered', function (): void {
        $invitation = WorkspaceInvitation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'newuser@example.com',
            'role' => Roles::Admin->value,
        ]);

        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $newUser = User::query()->whereEmail('newuser@example.com')->first();
        expect($newUser)->not->toBeNull();

        $this->assertDatabaseHas('workspace_users', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newUser->id,
            'role' => Roles::Admin->value,
        ]);
    });

    it('returns error for invalid token', function (): void {
        $response = $this->postJson(route('invitations.accept', ['token' => 'invalid-token']));

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Invalid invitation');
    });

    it('returns error for expired invitation', function (): void {
        $invitation = WorkspaceInvitation::factory()->expired()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson(route('invitations.accept', ['token' => $invitation->token]));

        $response->assertStatus(410)
            ->assertJsonPath('message', 'Invitation has expired');
    });

    it('returns error for already accepted invitation', function (): void {
        $invitation = WorkspaceInvitation::factory()->accepted()->create([
            'workspace_id' => $this->workspace->id,
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson(route('invitations.accept', ['token' => $invitation->token]));

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Invitation already used');
    });
});
