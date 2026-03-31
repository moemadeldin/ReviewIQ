<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class WorkspaceSwitchController
{
    public function __invoke(Request $request, string $workspaceId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Workspace|null $workspace */
        $workspace = $user->workspaces()->where('workspace_id', $workspaceId)->first();

        abort_if($workspace === null, 403);

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('dashboard');
    }
}
