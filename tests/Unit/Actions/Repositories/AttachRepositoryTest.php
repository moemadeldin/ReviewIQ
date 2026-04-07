<?php

declare(strict_types=1);

use App\Actions\Repositories\AttachRepository;
use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();

    $this->user = User::factory()->create([
        'github_token' => 'fake-token',
    ]);
});

it('attaches a repository successfully', function (): void {
    $fullName = 'owner/repo';

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldReceive('getUserRepos')
        ->once()
        ->with('fake-token')
        ->andReturn([
            [
                'id' => 123,
                'full_name' => $fullName,
                'language' => 'PHP',
            ],
        ]);

    $githubMock->shouldReceive('registerWebhook')
        ->once()
        ->with('fake-token', $fullName)
        ->andReturn(999);

    $action = new AttachRepository($githubMock);

    $repository = $action->handle($this->workspace, $this->user, $fullName);

    expect($repository)
        ->toBeInstanceOf(Repository::class)
        ->and($repository->workspace_id)->toBe($this->workspace->id)
        ->and($repository->github_repo_id)->toBe('123')
        ->and($repository->full_name)->toBe($fullName)
        ->and($repository->language)->toBe('PHP')
        ->and($repository->is_active)->toBeTrue()
        ->and($repository->webhook_id)->toBe('999');

    $this->assertDatabaseHas('repositories', [
        'github_repo_id' => '123',
        'full_name' => $fullName,
    ]);
});

it('throws 404 if repository is not found', function (): void {
    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldReceive('getUserRepos')
        ->once()
        ->with('fake-token')
        ->andReturn([]);

    $githubMock->shouldNotReceive('registerWebhook');

    $action = new AttachRepository($githubMock);

    $action->handle($this->workspace, $this->user, 'missing/repo');
})->throws(NotFoundHttpException::class, 'Repository not found');

it('handles missing language gracefully', function (): void {
    $fullName = 'owner/repo';

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldReceive('getUserRepos')
        ->once()
        ->andReturn([
            [
                'id' => 456,
                'full_name' => $fullName,
            ],
        ]);

    $githubMock->shouldReceive('registerWebhook')
        ->once()
        ->andReturn(111);

    $action = new AttachRepository($githubMock);

    $repository = $action->handle($this->workspace, $this->user, $fullName);

    expect($repository->language)->toBeNull();
});

it('reactivates existing inactive repository', function (): void {
    $fullName = 'owner/repo';

    $existingRepo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => $fullName,
        'is_active' => false,
        'github_repo_id' => '789',
    ]);

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldNotReceive('getUserRepos');
    $githubMock->shouldNotReceive('registerWebhook');

    $action = new AttachRepository($githubMock);

    $repository = $action->handle($this->workspace, $this->user, $fullName);

    expect($repository->id)->toBe($existingRepo->id)
        ->and($repository->is_active)->toBeTrue();

    $this->assertDatabaseMissing('repositories', [
        'id' => $existingRepo->id,
        'is_active' => false,
    ]);
});

it('does not call GitHub API when reactivating existing repository', function (): void {
    $fullName = 'owner/repo';

    Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => $fullName,
        'is_active' => false,
    ]);

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldNotReceive('getUserRepos');
    $githubMock->shouldNotReceive('registerWebhook');

    $action = new AttachRepository($githubMock);

    $action->handle($this->workspace, $this->user, $fullName);
});
