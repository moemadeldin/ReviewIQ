<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Repository;
use App\Models\Workspace;
use App\Services\GitHubApiService;
use Illuminate\Support\Facades\Auth;

final readonly class GetRepositoriesData
{
    public function __construct(private GitHubApiService $github) {}

    public function handle(Workspace $workspace): array
    {
        $user = Auth::user();

        if (! $user?->github_token) {
            return ['repositories' => [], 'connected_repos' => []];
        }

        $githubRepos = $this->github->getUserRepos($user->github_token);

        $connectedRepos = Repository::query()
            ->where('workspace_id', $workspace->id)
            ->get()
            ->keyBy('full_name');
        \Log::info('GetRepositoriesData called', [
            'user_id' => $user->id,
            'has_token' => !!$user->github_token,
            'workspace_id' => $workspace->id,
        ]);
        return [
            'repositories' => $githubRepos,
            'connected_repos' => $connectedRepos,
        ];
    }
}
