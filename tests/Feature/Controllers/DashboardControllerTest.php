<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;

it('renders dashboard with stats and recent pull requests', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create(['name' => 'Acme']);
    $repo = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $pr = PullRequest::factory()->create(['repository_id' => $repo->id]);
    Review::factory()->create(['pull_request_id' => $pr->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.workspaces', 1)
            ->where('stats.repositories', 1)
            ->where('stats.pullRequests', 1)
            ->where('stats.reviews', 1)
            ->has('recentPullRequests', 1));
});

it('scopes dashboard data to the authenticated user workspaces', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $workspace = Workspace::factory()->withOwner($user)->create();
    $otherWorkspace = Workspace::factory()->withOwner($otherUser)->create();

    $repo = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Repository::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $pr = PullRequest::factory()->create(['repository_id' => $repo->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.workspaces', 1)
            ->where('stats.repositories', 1)
            ->where('stats.pullRequests', 1)
            ->has('recentPullRequests', 1)
            ->where('recentPullRequests.0.workspace_id', $workspace->id));
});
