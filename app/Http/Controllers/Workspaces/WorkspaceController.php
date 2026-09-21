<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\WorkspaceOwnerRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetWorkspaceTabCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkspaceController
{
    public function __construct(
        private GetWorkspaceTabCounts $getTabCounts,
    ) {}

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

        $workspace = $action->handle(
            user: $user,
            name: $request->string('name')->toString(),
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.index');
    }

    public function show(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/show', [
            'workspace' => $workspace,
            'tabCounts' => $this->getTabCounts->handle($workspace),
        ]);
    }

    public function settings(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/settings', [
            'workspace' => $workspace,
            'tabCounts' => $this->getTabCounts->handle($workspace),
        ]);
    }

    public function update(StoreWorkspaceRequest $request, Workspace $workspace, UpdateWorkspace $action): RedirectResponse
    {

        $action->handle($workspace, $request->string('name')->toString());

        return to_route('workspaces.show', $workspace);
    }

    public function destroy(WorkspaceOwnerRequest $request, #[CurrentUser] User $user, Workspace $workspace, DeleteWorkspace $action): RedirectResponse
    {
        $request->session()->forget('current_workspace_id');
        $action->handle($workspace, $user);

        return to_route('workspaces.index');
    }
}
