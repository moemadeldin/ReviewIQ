<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class SwitchWorkspaceController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, Workspace $workspace): RedirectResponse
    {
        if (! $user->canAccessWorkspace($workspace)) {
            return to_route('dashboard');
        }

        $request->session()->put('current_workspace_id', $workspace->id);

        return back();
    }
}
