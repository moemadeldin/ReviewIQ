<?php

declare(strict_types=1);

use App\Actions\Repositories\DeleteRepository;
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

it('deletes repository successfully and sets is_active to false', function (): void {
    $fullName = 'owner/repo';

    $repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => $fullName,
        'is_active' => true,
        'webhook_id' => '12345',
    ]);

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldReceive('deleteWebhook')
        ->once()
        ->with('fake-token', $fullName, '12345');

    $action = new DeleteRepository($githubMock);

    $action->handle($this->workspace, $this->user, $fullName);

    expect($repository->fresh()->is_active)->toBeFalse();

    $this->assertDatabaseHas('repositories', [
        'id' => $repository->id,
        'is_active' => false,
    ]);
});

it('does not call deleteWebhook when webhook_id is null', function (): void {
    $fullName = 'owner/repo';

    $repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => $fullName,
        'is_active' => true,
        'webhook_id' => null,
    ]);

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldNotReceive('deleteWebhook');

    $action = new DeleteRepository($githubMock);

    $action->handle($this->workspace, $this->user, $fullName);

    expect($repository->fresh()->is_active)->toBeFalse();
});

it('throws 404 when repository not found', function (): void {
    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldNotReceive('deleteWebhook');

    $action = new DeleteRepository($githubMock);

    $action->handle($this->workspace, $this->user, 'nonexistent/repo');
})->throws(NotFoundHttpException::class, 'Repository not found');

it('throws 404 when repository belongs to different workspace', function (): void {
    $fullName = 'owner/repo';

    $otherWorkspace = Workspace::factory()->create();

    $repository = Repository::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'full_name' => $fullName,
        'is_active' => true,
    ]);

    $githubMock = Mockery::mock(GitHubApi::class);

    $githubMock->shouldNotReceive('deleteWebhook');

    $action = new DeleteRepository($githubMock);

    $action->handle($this->workspace, $this->user, $fullName);
})->throws(NotFoundHttpException::class, 'Repository not found');
