<?php

declare(strict_types=1);

namespace App\Actions\Repositories;

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Response;

final readonly class AttachRepository
{
    public function __construct(private GitHubApi $github) {}

    public function handle(Workspace $workspace, User $user, string $fullName): Repository
    {
        $existing = Repository::query()
            ->where('workspace_id', $workspace->id)
            ->where('full_name', $fullName)
            ->first();
        if ($existing) {
            $existing->update(['is_active' => true]);

            return $existing;
        }

        /** @var string $token */
        $token = $user->github_token;
        $githubRepos = $this->github->getUserRepos($token);
        $repoData = collect($githubRepos)->firstWhere('full_name', $fullName);

        abort_unless(is_array($repoData), Response::HTTP_NOT_FOUND, 'Repository not found');

        $webhookId = $this->github->registerWebhook($token, $fullName);

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
