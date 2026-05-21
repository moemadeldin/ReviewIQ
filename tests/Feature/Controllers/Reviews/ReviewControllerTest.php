<?php

declare(strict_types=1);

use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;

it('returns pull requests with reviews for workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $repo = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Reviewed,
        'title' => 'Test PR',
        'number' => 1,
    ]);

    Review::factory()->create([
        'pull_request_id' => $pr->id,
        'score' => 8,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('reviews.index', ['workspace' => $workspace->slug]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'pull_requests',
                'current_page',
                'has_more',
            ],
        ]);
});

it('returns empty list when no pull requests', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('reviews.index', ['workspace' => $workspace->slug]));

    $response->assertOk()
        ->assertJsonPath('data.pull_requests', [])
        ->assertJsonPath('data.has_more', false);
});

it('returns pull request detail with review', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $repo = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Reviewed,
        'title' => 'Detailed PR',
        'number' => 42,
    ]);

    Review::factory()->create([
        'pull_request_id' => $pr->id,
        'score' => 9,
        'summary' => 'Great PR!',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->get(route('reviews.show', [
            'workspace' => $workspace->slug,
            'pullRequest' => $pr->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/show')
            ->where('pullRequest.title', 'Detailed PR'));
});

it('returns pull request detail as json', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $repo = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $pr = PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Reviewed,
        'title' => 'API PR',
        'number' => 99,
    ]);

    Review::factory()->create([
        'pull_request_id' => $pr->id,
        'score' => 8,
        'summary' => 'Good work',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('reviews.show.data', [
            'workspace' => $workspace->slug,
            'pullRequest' => $pr->id,
        ]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'pull_request',
            ],
        ])
        ->assertJsonPath('data.pull_request.title', 'API PR');
});

it('filters pull requests by repository', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $repo1 = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace->id]);

    PullRequest::factory()->create(['repository_id' => $repo1->id]);
    PullRequest::factory()->create(['repository_id' => $repo2->id]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('reviews.index', [
            'workspace' => $workspace->slug,
            'repository_id' => $repo1->id,
        ]));

    $response->assertOk();
});

it('filters pull requests by status', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $repo = Repository::factory()->create(['workspace_id' => $workspace->id]);

    PullRequest::factory()->create([
        'repository_id' => $repo->id,
        'status' => PullRequestStatus::Reviewed,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('reviews.index', [
            'workspace' => $workspace->slug,
            'status' => 'reviewed',
        ]));

    $response->assertOk();
});
