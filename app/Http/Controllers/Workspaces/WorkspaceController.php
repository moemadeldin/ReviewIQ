<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceController
{
    public function index(#[CurrentUser()] User $user): Response
    {
        return Inertia::render('workspaces/index', [
            'workspaces' => $user->workspaces,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('workspaces/create');
    }

    public function store(StoreWorkspaceRequest $request, #[CurrentUser] User $user, CreateWorkspace $action): RedirectResponse
    {
        /** @var string $name */
        $name = $request->safe()->name;

        $workspace = $action->handle(
            user: $user,
            name: $name,
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('dashboard');
    }

    public function show(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/show', [
            'workspace' => $workspace,
        ]);
    }
}
