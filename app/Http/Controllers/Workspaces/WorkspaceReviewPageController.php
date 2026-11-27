<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Resources\PullRequestDetailResource;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceReviewPageController
{
    public function __invoke(#[CurrentUser()] User $user, Workspace $workspace, PullRequest $pullRequest): Response
    {
        $userRole = $workspace->roleOf($user);
        $pullRequest->load(['repository', 'review']);

        return Inertia::render('reviews/show', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'pullRequest' => new PullRequestDetailResource($pullRequest)->resolve(),
        ]);
    }
}
