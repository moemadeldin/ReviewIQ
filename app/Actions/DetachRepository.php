<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Repository;
use App\Models\Workspace;
use App\Services\GitHubApiService;
use Illuminate\Support\Facades\Auth;

final readonly class DeleteRepositoryAction
{
    public function __construct(private GitHubApiService $github) {}

    public function handle(Workspace $workspace, string $fullName): void
    {
        $user = Auth::user();

        $repository = Repository::query()
            ->where('workspace_id', $workspace->id)
            ->where('full_name', $fullName)
            ->first();

        if (! $repository) {
            abort(404, 'Repository not found');
        }

        if ($repository->webhook_id) {
            $this->github->deleteWebhook($user->github_token, $fullName, $repository->webhook_id);
        }

        $repository->update(['is_active' => false]);
    }
}
