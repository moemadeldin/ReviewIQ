<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Repository;
use App\Models\Workspace;
use App\Services\GitHubApiService;
use Illuminate\Support\Facades\Auth;

final readonly class StoreRepositoryAction
{
    public function __construct(private GitHubApiService $github) {}

    public function handle(Workspace $workspace, string $fullName): Repository
    {
        $user = Auth::user();

        $githubRepos = $this->github->getUserRepos($user->github_token);
        $repoData = collect($githubRepos)->firstWhere('full_name', $fullName);

        if (! $repoData) {
            abort(404, 'Repository not found');
        }

        $webhookId = $this->github->registerWebhook($user->github_token, $fullName);

        return Repository::query()->create([
            'workspace_id' => $workspace->id,
            'github_repo_id' => (string) $repoData['id'],
            'full_name' => $fullName,
            'language' => $repoData['language'] ?? null,
            'is_active' => true,
            'webhook_id' => $webhookId,
        ]);
    }
}
