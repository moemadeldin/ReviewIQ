<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            /** @var Response */
            return $next($request);
        }

        $workspaceId = null;

        if ($request->hasSession()) {
            $workspaceId = $request->session()->get('current_workspace_id');
        }

        /** @var Workspace|null $workspace */
        $workspace = $workspaceId
            ? $user->workspaces()->where('workspace_id', $workspaceId)->first()
            : null;

        if ($workspace === null) {
            $workspace = $user->workspaces()->first();
        }

        if ($workspace !== null) {
            if ($request->hasSession()) {
                $request->session()->put('current_workspace_id', $workspace->getKey());
            }

            $request->attributes->set('current_workspace', $workspace);
        }

        /** @var Response */
        return $next($request);
    }
}
