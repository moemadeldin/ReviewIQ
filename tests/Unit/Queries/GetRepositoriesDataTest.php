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

it('filters repos by search language and visibility within 100 fetched repos', function (): void {
    $user = User::factory()->create(['github_token' => 'test-token']);

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1, 100)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/blog-api', 'name' => 'blog-api', 'language' => 'PHP', 'private' => true],
            ['id' => 2, 'full_name' => 'owner/cli-tool', 'name' => 'cli-tool', 'language' => 'PHP', 'private' => false],
            ['id' => 3, 'full_name' => 'owner/web-app', 'name' => 'web-app', 'language' => 'TypeScript', 'private' => false],
        ]);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, null, 1, 'api', 'php', 'private');

    expect($result['repositories'])->toHaveCount(1)
        ->and($result['repositories'][0]['full_name'])->toBe('owner/blog-api')
        ->and($result['has_more'])->toBeFalse();
});

it('paginates filtered repos to 10 per page', function (): void {
    $user = User::factory()->create(['github_token' => 'test-token']);
    $repos = collect(range(1, 12))->map(fn (int $i): array => [
        'id' => $i,
        'full_name' => 'owner/repo'.$i,
        'name' => 'repo'.$i,
        'language' => 'PHP',
        'private' => false,
    ])->all();

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1, 100)
        ->andReturn($repos);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, null, 1, 'repo', null, null);

    expect($result['repositories'])->toHaveCount(10)
        ->and($result['has_more'])->toBeTrue();
});

it('returns the remaining filtered repos on the second page', function (): void {
    $user = User::factory()->create(['github_token' => 'test-token']);
    $repos = collect(range(1, 12))->map(fn (int $i): array => [
        'id' => $i,
        'full_name' => 'owner/repo'.$i,
        'name' => 'repo'.$i,
        'language' => 'PHP',
        'private' => false,
    ])->all();

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1, 100)
        ->andReturn($repos);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, null, 2, 'repo', null, null);

    expect($result['repositories'])->toHaveCount(2)
        ->and($result['has_more'])->toBeFalse();
});

it('returns empty filtered results when nothing matches', function (): void {
    $user = User::factory()->create(['github_token' => 'test-token']);

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1, 100)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/blog', 'name' => 'blog', 'language' => 'PHP', 'private' => false],
        ]);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, null, 1, 'nonexistent', null, null);

    expect($result['repositories'])->toBe([])
        ->and($result['has_more'])->toBeFalse();
});

it('filters repos scoped to a workspace after excluding active repos elsewhere', function (): void {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create(['github_token' => 'test-token']);
    Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/web-app',
        'is_active' => true,
    ]);

    $github = $this->mock(GitHubApi::class);
    $github->shouldReceive('getUserRepos')
        ->once()
        ->with('test-token', 1, 100)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/web-app', 'name' => 'web-app', 'language' => 'TypeScript', 'private' => false],
            ['id' => 2, 'full_name' => 'owner/cli-tool', 'name' => 'cli-tool', 'language' => 'PHP', 'private' => false],
        ]);

    $query = new GetRepositoriesData($github);
    $result = $query->handle($user, $workspace, 1, 'tool', null, null);

    expect($result['repositories'])->toHaveCount(1)
        ->and($result['repositories'][0]['full_name'])->toBe('owner/cli-tool')
        ->and($result['connected_repos'])->toHaveKeys(['owner/web-app']);
});
