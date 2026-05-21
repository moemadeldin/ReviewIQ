<?php

declare(strict_types=1);

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetRepositoriesData;

it('returns empty repos when user has no github token', function (): void {
    $user = User::factory()->create(['github_token' => null]);

    $query = resolve(GetRepositoriesData::class);
    $result = $query->handle($user);

    expect($result['repositories'])->toBe([])
        ->and($result['connected_repos'])->toBe([])
        ->and($result['has_more'])->toBeFalse()
        ->and($result['current_page'])->toBe(1);
});

it('fetches repos from github and scopes to workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create(['github_token' => 'test-token']);
    $repo = Repository::factory()->create(['workspace_id' => $workspace->id, 'full_name' => 'owner/repo1']);

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/repo1', 'language' => 'PHP'],
            ['id' => 2, 'full_name' => 'owner/repo2', 'language' => 'JS'],
        ]);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, $workspace);

    expect($result['repositories'])->toHaveCount(2)
        ->and($result['connected_repos'])->toHaveKeys(['owner/repo1'])
        ->and($result['has_more'])->toBeFalse();
});

it('fetches repos from all user workspaces when no workspace given', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();
    $user = User::factory()->create(['github_token' => 'test-token']);
    $user->workspaces()->attach($workspace1->id, ['role' => 'member']);
    $user->workspaces()->attach($workspace2->id, ['role' => 'member']);

    Repository::factory()->create(['workspace_id' => $workspace1->id, 'full_name' => 'owner/repo1']);
    Repository::factory()->create(['workspace_id' => $workspace2->id, 'full_name' => 'owner/repo2']);

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/repo1', 'language' => 'PHP'],
            ['id' => 2, 'full_name' => 'owner/repo2', 'language' => 'PHP'],
        ]);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user);

    expect($result['connected_repos'])->toHaveCount(2)
        ->and($result['connected_repos'])->toHaveKeys(['owner/repo1', 'owner/repo2']);
});

it('sets has_more when exactly 10 repos returned', function (): void {
    $user = User::factory()->create(['github_token' => 'test-token']);
    $repos = collect(range(1, 10))->map(fn (int $i): array => [
        'id' => $i, 'full_name' => 'owner/repo'.$i, 'language' => 'PHP',
    ])->all();

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->andReturn($repos);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user);

    expect($result['has_more'])->toBeTrue();
});
