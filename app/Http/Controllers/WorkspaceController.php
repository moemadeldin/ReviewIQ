<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateWorkspace;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceController
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

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
        $name = $request->validated('name');

        $workspace = $action->handle(
            owner: $user,
            name: $name,
            slug: $request->slug(),
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('dashboard');
    }

    public function show(Request $request, string $workspace): Response
    {
        $workspaceModel = Workspace::where('slug', $workspace)->firstOrFail();

        return Inertia::render('workspaces/show', [
            'workspace' => $workspaceModel,
        ]);
    }
}
