<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withOwner($this->user)->create();
});

it('renders members page', function (): void {
    $member = User::factory()->create(['name' => 'Alice']);
    $this->workspace->addUser($member, Roles::Admin);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('workspaces.members.page', ['workspace' => $this->workspace->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/members')
            ->has('workspace')
            ->has('userRole')
            ->has('initialMembers')
            ->has('membersCurrentPage')
            ->has('membersHasMore'));
});

it('renders repos page', function (): void {
    $repo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('workspaces.repos.page', ['workspace' => $this->workspace->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/repos')
            ->has('workspace')
            ->has('userRole')
            ->has('initialRepos')
            ->has('reposCurrentPage')
            ->has('reposHasMore'));
});

it('renders invitations page', function (): void {
    $invitation = WorkspaceInvitation::factory()->forWorkspace($this->workspace)->create();

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('workspaces.invitations.page', ['workspace' => $this->workspace->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/invitations')
            ->has('workspace')
            ->has('userRole')
            ->has('initialInvitations')
            ->has('invitationsCurrentPage')
            ->has('invitationsHasMore'));
});

it('renders reviews index page', function (): void {
    $repo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('reviews.page', ['workspace' => $this->workspace->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/index')
            ->has('workspace')
            ->has('userRole')
            ->has('initialPullRequests')
            ->has('repositories')
            ->has('filters'));
});

it('renders reviews index page with filters', function (): void {
    $repo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('reviews.page', [
            'workspace' => $this->workspace->slug,
            'repository_id' => $repo->id,
            'status' => 'pending',
        ]));

    $response->assertOk();
});

it('renders review detail page', function (): void {
    $repo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'title' => 'Test PR',
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('reviews.show', [
            'workspace' => $this->workspace->slug,
            'pullRequest' => $pr->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/show')
            ->has('workspace')
            ->has('userRole')
            ->has('pullRequest'));
});

it('includes user role in all pages', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get(route('workspaces.members.page', ['workspace' => $this->workspace->slug]));

    $response->assertInertia(fn ($page) => $page
        ->where('userRole', 'owner'));
});
