<?php

declare(strict_types=1);

use App\Actions\WorkspaceInvitations\CreateInvitationAction;
use App\Enums\Roles;
use App\Jobs\SendInvitationEmail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

it('creates invitation and dispatches email job', function (): void {
    Bus::fake();
    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();

    $action = resolve(CreateInvitationAction::class);
    $invitation = $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'test@example.com',
        role: Roles::Member,
    );

    expect($invitation)->toBeInstanceOf(WorkspaceInvitation::class)
        ->and($invitation->workspace_id)->toBe($workspace->id)
        ->and($invitation->email)->toBe('test@example.com')
        ->and($invitation->role)->toBe(Roles::Member)
        ->and($invitation->token)->not->toBeEmpty()
        ->and($invitation->expires_at)->not->toBeNull();

    Bus::assertDispatched(SendInvitationEmail::class);
});

it('creates invitation with role as string', function (): void {
    Bus::fake();
    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();

    $action = resolve(CreateInvitationAction::class);
    $invitation = $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'test@example.com',
        role: 'admin',
    );

    expect($invitation->role)->toBe(Roles::Member);
});

it('throws if user is already a member', function (): void {
    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $workspace->users()->attach($existingUser->id, ['role' => 'member']);

    $action = resolve(CreateInvitationAction::class);

    expect(fn () => $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'existing@example.com',
        role: Roles::Member,
    ))->toThrow(RuntimeException::class, 'User is already a member of this workspace');
});

it('throws if a non-expired invitation already exists', function (): void {
    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addDays(1),
        'accepted_at' => null,
    ]);

    $action = resolve(CreateInvitationAction::class);

    expect(fn () => $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'pending@example.com',
        role: Roles::Member,
    ))->toThrow(RuntimeException::class, 'Invitation already sent to this email');
});

it('notifies existing user when sending invitation', function (): void {
    Bus::fake();
    Notification::fake();

    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $action = resolve(CreateInvitationAction::class);
    $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'existing@example.com',
        role: Roles::Member,
    );

    Notification::assertSentTo(
        $existingUser,
        WorkspaceInvitationNotification::class,
    );
});

it('falls back to url when route is not found', function (): void {
    Bus::fake();

    $urlFactory = resolve(UrlGenerator::class);
    $mock = Mockery::mock($urlFactory)->makePartial();
    $mock->shouldReceive('route')
        ->andThrow(new RouteNotFoundException());
    app()->instance('url', $mock);

    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();

    $action = resolve(CreateInvitationAction::class);
    $invitation = $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'new@example.com',
        role: Roles::Member,
    );

    expect($invitation->token)->not->toBeEmpty();
    Bus::assertDispatched(SendInvitationEmail::class);
});

it('creates invitation with default member role when using string role', function (): void {
    Bus::fake();
    $workspace = Workspace::factory()->create();
    $invitedBy = User::factory()->create();

    $action = resolve(CreateInvitationAction::class);
    $invitation = $action->handle(
        workspace: $workspace,
        invitedBy: $invitedBy,
        email: 'test@example.com',
        role: 'unknown-role',
    );

    expect($invitation->role)->toBe(Roles::Member);
});
