<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
final readonly class WorkspaceSwitchController
{
    public function __invoke(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_if($workspace === null, Response::HTTP_FORBIDDEN);

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('dashboard');
    }
}
