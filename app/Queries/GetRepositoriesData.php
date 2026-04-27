<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetRepositoriesData
{
    public function __construct(private GitHubApi $github) {}

    /**
     * @return array{repositories: array<int, array{id: int, full_name: string, language: string|null}>, connected_repos: Collection<string, Repository>, has_more: bool, current_page: int}
     */
    public function handle(User $user, ?Workspace $workspace = null, int $page = 1): array
    {
        if (! $user->github_token) {
            return ['repositories' => [], 'connected_repos' => new Collection(), 'has_more' => false, 'current_page' => $page];
        }

        $githubRepos = $this->github->getUserRepos($user->github_token, $page);

        if ($workspace instanceof Workspace) {
            $connectedRepos = Repository::query()
                ->where('workspace_id', $workspace->id)
                ->get()
                ->keyBy('full_name');
        } else {
            $connectedRepos = Repository::query()
                ->whereIn('workspace_id', $user->workspaces()->pluck('workspace_id'))
                ->get()
                ->keyBy('full_name');
        }

        $hasMore = count($githubRepos) === 10;

        return [
            'repositories' => $githubRepos,
            'connected_repos' => $connectedRepos,
            'has_more' => $hasMore,
            'current_page' => $page,
        ];
    }
}
