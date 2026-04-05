<?php

declare(strict_types=1);

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetRepositoriesData;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'github_token' => 'fake-token',
    ]);
    $this->workspace = Workspace::factory()->create();
});

it('returns empty array when user has no github_token', function (): void {
    $userWithoutToken = User::factory()->create([
        'github_token' => null,
    ]);

    $query = new GetRepositoriesData(Mockery::mock(GitHubApi::class));

    $result = $query->handle($userWithoutToken, $this->workspace);

    expect($result)->toBe([
        'repositories' => [],
        'connected_repos' => [],
        'has_more' => false,
    ]);
});

it('returns github repos and connected repos', function (): void {
    $githubMock = Mockery::mock(GitHubApi::class);
    $githubMock->shouldReceive('getUserRepos')
        ->with('fake-token', 1)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/repo1', 'language' => 'PHP'],
            ['id' => 2, 'full_name' => 'owner/repo2', 'language' => 'JavaScript'],
            ['id' => 3, 'full_name' => 'owner/repo3', 'language' => 'Python'],
            ['id' => 4, 'full_name' => 'owner/repo4', 'language' => 'Ruby'],
            ['id' => 5, 'full_name' => 'owner/repo5', 'language' => 'Go'],
            ['id' => 6, 'full_name' => 'owner/repo6', 'language' => 'Rust'],
            ['id' => 7, 'full_name' => 'owner/repo7', 'language' => 'TypeScript'],
            ['id' => 8, 'full_name' => 'owner/repo8', 'language' => 'C++'],
            ['id' => 9, 'full_name' => 'owner/repo9', 'language' => 'C#'],
            ['id' => 10, 'full_name' => 'owner/repo10', 'language' => 'Java'],
        ]);

    $connectedRepo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => 'owner/repo1',
        'is_active' => true,
    ]);

    $query = new GetRepositoriesData($githubMock);

    $result = $query->handle($this->user, $this->workspace);

    expect($result['repositories'])->toHaveCount(10)
        ->and($result['connected_repos'])->toHaveCount(1)
        ->and($result['connected_repos']['owner/repo1']->id)->toBe($connectedRepo->id)
        ->and($result['has_more'])->toBeTrue()
        ->and($result['current_page'])->toBe(1);
});

it('returns has_more false when less than 10 repos', function (): void {
    $githubMock = Mockery::mock(GitHubApi::class);
    $githubMock->shouldReceive('getUserRepos')
        ->with('fake-token', 1)
        ->andReturn([
            ['id' => 1, 'full_name' => 'owner/repo1', 'language' => 'PHP'],
        ]);

    $query = new GetRepositoriesData($githubMock);

    $result = $query->handle($this->user, $this->workspace);

    expect($result['has_more'])->toBeFalse();
});

it('fetches with correct page parameter', function (): void {
    $githubMock = Mockery::mock(GitHubApi::class);
    $githubMock->shouldReceive('getUserRepos')
        ->with('fake-token', 3)
        ->andReturn([]);

    $query = new GetRepositoriesData($githubMock);

    $result = $query->handle($this->user, $this->workspace, 3);

    expect($result['current_page'])->toBe(3);
});
