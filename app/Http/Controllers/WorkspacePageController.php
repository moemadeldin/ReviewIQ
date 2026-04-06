<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspacePageController
{
    public function members(#[CurrentUser()] User $user, string $workspace): Response
    {
        $workspaceModel = Workspace::where('slug', $workspace)->firstOrFail();
        $userRole = $workspaceModel->roleOf($user);

        return Inertia::render('workspaces/members', [
            'workspace' => $workspaceModel,
            'userRole' => $userRole,
        ]);
    }

    public function repos(#[CurrentUser()] User $user, string $workspace): Response
    {
        $workspaceModel = Workspace::where('slug', $workspace)->firstOrFail();
        $userRole = $workspaceModel->roleOf($user);

        return Inertia::render('workspaces/repos', [
            'workspace' => $workspaceModel,
            'userRole' => $userRole,
        ]);
    }

    public function invitations(#[CurrentUser()] User $user, string $workspace): Response
    {
        $workspaceModel = Workspace::where('slug', $workspace)->firstOrFail();
        $userRole = $workspaceModel->roleOf($user);

        return Inertia::render('workspaces/invitations', [
            'workspace' => $workspaceModel,
            'userRole' => $userRole,
        ]);
    }
}
