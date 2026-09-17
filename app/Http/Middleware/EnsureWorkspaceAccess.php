<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $workspace = $request->route('workspace');

        if (is_string($workspace)) {
            $workspace = (new Workspace)->resolveRouteBinding($workspace);
        }

        if (! $user instanceof User || ! $workspace instanceof Workspace) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        if (! $workspace->isOwner($user) && ! $user->canAccessWorkspace($workspace)) {
            return to_route('dashboard');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
