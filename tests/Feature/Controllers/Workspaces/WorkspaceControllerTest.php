<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;

it('renders workspace creation page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('workspaces.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/create'));
});

it('creates a workspace', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('workspaces.create')
        ->post(route('workspaces.store'), [
            'name' => 'Acme Inc',
        ]);

    $response->assertRedirectToRoute('dashboard');

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Acme Inc',
        'slug' => 'acme-inc',
        'owner_id' => $user->id,
    ]);

    $this->assertDatabaseHas('workspace_users', [
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

it('requires workspace name', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('workspaces.create')
        ->post(route('workspaces.store'), []);

    $response->assertRedirectToRoute('workspaces.create')
        ->assertSessionHasErrors('name');
});

it('sets workspace as current after creation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('workspaces.create')
        ->post(route('workspaces.store'), [
            'name' => 'My Workspace',
        ]);

    $workspace = Workspace::query()->where('owner_id', $user->id)->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('current_workspace_id'))->toBe($workspace->id);
});

it('renders workspace index page', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->get(route('workspaces.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/index')
            ->has('workspaces'));
});

it('user A cannot read workspace B data', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $workspaceA = Workspace::factory()->withOwner($userA)->create(['name' => 'Workspace A']);
    $workspaceB = Workspace::factory()->withOwner($userB)->create(['name' => 'Workspace B']);

    // Create some data in workspace B that uses inWorkspace scope
    // Since we don't have tenant models yet, we test the workspace isolation directly
    expect($userA->workspaces)->toHaveCount(1)
        ->and($userA->workspaces->first()->id)->toBe($workspaceA->id);

    expect($userB->workspaces)->toHaveCount(1)
        ->and($userB->workspaces->first()->id)->toBe($workspaceB->id);

    // User A should not be able to access workspace B
    expect($userA->canAccessWorkspace($workspaceB))->toBeFalse();
    expect($userB->canAccessWorkspace($workspaceA))->toBeFalse();

    // Each user can access their own workspace
    expect($userA->canAccessWorkspace($workspaceA))->toBeTrue();
    expect($userB->canAccessWorkspace($workspaceB))->toBeTrue();
});

it('workspace slug is unique per owner', function (): void {
    $user = User::factory()->create();

    Workspace::factory()->withOwner($user)->create([
        'name' => 'Acme Inc',
        'slug' => 'acme-inc',
    ]);

    // Attempting to create a workspace with the same slug for the same owner should fail
    $this->expectException(QueryException::class);

    Workspace::factory()->withOwner($user)->create([
        'name' => 'Acme Inc',
        'slug' => 'acme-inc',
    ]);
});

it('different owners can have same slug', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Workspace::factory()->withOwner($userA)->create([
        'name' => 'Acme Inc',
        'slug' => 'acme-inc',
    ]);

    // Different owner with same slug should work
    $workspaceB = Workspace::factory()->withOwner($userB)->create([
        'name' => 'Acme Inc',
        'slug' => 'acme-inc',
    ]);

    expect($workspaceB->slug)->toBe('acme-inc');
});

it('new user is redirected to workspace creation', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirectToRoute('workspaces.create');
});

it('user with workspace is not redirected', function (): void {
    $user = User::factory()->create();
    Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

it('owner is added to workspace users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('workspaces.create')
        ->post(route('workspaces.store'), [
            'name' => 'My Workspace',
        ]);

    $workspace = Workspace::query()->where('owner_id', $user->id)->first();

    expect($workspace->roleOf($user))->toBe(Roles::Owner);
});

it('renders workspace show page', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->get(route('workspaces.show', $workspace));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/show')
            ->has('workspace'));
});

it('auto-generates slug from workspace name', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('workspaces.create')
        ->post(route('workspaces.store'), [
            'name' => 'My Awesome Workspace!',
        ]);

    $this->assertDatabaseHas('workspaces', [
        'name' => 'My Awesome Workspace!',
        'slug' => 'my-awesome-workspace',
        'owner_id' => $user->id,
    ]);
});
