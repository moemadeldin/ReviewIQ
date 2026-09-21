<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Utilities\Constants;

final readonly class GetRepositoriesData
{
    public function __construct(private GitHubApi $github) {}

    /**
     * @return array{repositories: array<int, array{id: int, full_name: string, name: string, language: string|null, private: bool}>, connected_repos: array<string, Repository>, has_more: bool, current_page: int}
     */
    public function handle(
        User $user,
        ?Workspace $workspace = null,
        int $page = 1,
        ?string $search = null,
        ?string $language = null,
        ?string $visibility = null,
    ): array {
        if (! $user->github_token) {
            return ['repositories' => [], 'connected_repos' => [], 'has_more' => false, 'current_page' => $page];
        }

        $hasFilters = $search !== null && $search !== ''
            || $language !== null && $language !== ''
            || $visibility !== null && $visibility !== '';

        if ($hasFilters) {
            $githubRepos = $this->github->getUserRepos($user->github_token, 1, 100);
        } else {
            $githubRepos = $this->github->getUserRepos($user->github_token, $page);
        }

        if ($workspace instanceof Workspace) {
            $connectedRepos = [];
            foreach (Repository::query()->where('workspace_id', $workspace->id)->get() as $repository) {
                $connectedRepos[$repository->full_name] = $repository;
            }

            $allActiveFullNames = Repository::query()
                ->where('is_active', true)
                ->pluck('full_name')
                ->toArray();

            $githubRepos = collect($githubRepos)
                ->reject(fn (array $repo): bool => in_array($repo['full_name'], $allActiveFullNames, true)
                    && ! isset($connectedRepos[$repo['full_name']]),
                )
                ->values()
                ->all();
        } else {
            $connectedRepos = [];
            foreach (Repository::query()->whereIn('workspace_id', $user->workspaces()->pluck('workspace_id'))->get() as $repository) {
                $connectedRepos[$repository->full_name] = $repository;
            }
        }

        $perPage = Constants::reposPerPage();

        if ($hasFilters) {
            $githubRepos = array_values(array_filter(
                $githubRepos,
                fn (array $repo): bool => $this->matchesFilters($repo, $search, $language, $visibility),
            ));

            $total = count($githubRepos);
            $githubRepos = array_slice($githubRepos, ($page - 1) * $perPage, $perPage);
            $hasMore = $total > $page * $perPage;
        } else {
            $hasMore = count($githubRepos) === $perPage;
        }

        return [
            'repositories' => array_values($githubRepos),
            'connected_repos' => $connectedRepos,
            'has_more' => $hasMore,
            'current_page' => $page,
        ];
    }

    /**
     * @param  array{id: int, full_name: string, name: string, language: string|null, private: bool}  $repo
     */
    private function matchesFilters(array $repo, ?string $search, ?string $language, ?string $visibility): bool
    {
        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $haystack = mb_strtolower(mb_trim($repo['full_name'].' '.$repo['name']));

            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        if ($language !== null && $language !== '' && mb_strtolower((string) ($repo['language'] ?? '')) !== mb_strtolower($language)) {
            return false;
        }

        if ($visibility !== null && $visibility !== '') {
            $isPrivate = $repo['private'];

            if ($visibility === 'public' && $isPrivate) {
                return false;
            }

            if ($visibility === 'private' && ! $isPrivate) {
                return false;
            }
        }

        return true;
    }
}
