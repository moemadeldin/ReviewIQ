<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;

it('sends via mail and database channels', function (): void {
    $invitedBy = User::factory()->create(['name' => 'Alice']);
    $workspace = Workspace::factory()->create(['name' => 'Test Workspace']);

    $notification = new WorkspaceInvitationNotification(
        workspace: $workspace,
        invitedBy: $invitedBy,
        acceptUrl: 'http://localhost/invitations/token/accept',
    );

    expect($notification->via($invitedBy))->toBe(['mail', 'database']);
});

it('builds mail message correctly', function (): void {
    $invitedBy = User::factory()->create(['name' => 'Alice']);
    $workspace = Workspace::factory()->create(['name' => 'Test Workspace']);

    $notification = new WorkspaceInvitationNotification(
        workspace: $workspace,
        invitedBy: $invitedBy,
        acceptUrl: 'http://localhost/invitations/token/accept',
    );

    $mail = $notification->toMail($invitedBy);

    expect($mail->subject)->toBe('You have been invited to join a workspace')
        ->and($mail->greeting)->toBe('Hello!')
        ->and($mail->introLines)->toHaveCount(1)
        ->and($mail->introLines[0])->toContain('Alice')
        ->and($mail->introLines[0])->toContain('Test Workspace');
});

it('returns correct array data for database channel', function (): void {
    $invitedBy = User::factory()->create(['name' => 'Alice']);
    $workspace = Workspace::factory()->create(['name' => 'Test Workspace', 'slug' => 'test-workspace']);

    $notification = new WorkspaceInvitationNotification(
        workspace: $workspace,
        invitedBy: $invitedBy,
        acceptUrl: 'http://localhost/invitations/token/accept',
    );

    $data = $notification->toArray($invitedBy);

    expect($data)->toHaveKeys(['title', 'message', 'workspace_id', 'workspace_slug', 'workspace_name', 'invited_by', 'accept_url'])
        ->and($data['title'])->toBe('Workspace Invitation')
        ->and($data['message'])->toContain('Alice')
        ->and($data['message'])->toContain('Test Workspace')
        ->and($data['workspace_slug'])->toBe('test-workspace')
        ->and($data['invited_by'])->toBe('Alice')
        ->and($data['accept_url'])->toBe('http://localhost/invitations/token/accept');
});
