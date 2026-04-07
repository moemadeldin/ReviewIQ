<?php

declare(strict_types=1);

namespace App\Actions\Repositories;

use App\Contracts\GitHubApi;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Response;

final readonly class DeleteRepository
{
    public function __construct(private GitHubApi $github) {}

    public function handle(Workspace $workspace, User $user, string $fullName): void
    {

        $repository = Repository::query()
            ->where('workspace_id', $workspace->id)
            ->where('full_name', $fullName)
            ->first();

        abort_unless($repository, Response::HTTP_NOT_FOUND, 'Repository not found');

        if ($repository->webhook_id) {
            $this->github->deleteWebhook($user->github_token, $fullName, $repository->webhook_id);
        }

        $repository->update(['is_active' => false]);
    }
}
