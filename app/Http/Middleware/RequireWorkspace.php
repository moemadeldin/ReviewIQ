<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
final readonly class RequireWorkspace
{
    /**
     * Routes that are allowed without a current workspace.
     *
     * @var list<string>
     */
    private const array EXCEPTED_ROUTES = [
        'workspaces.create',
        'workspaces.store',
        'workspaces.index',
        'workspaces.select',
        'workspaces.destroy',
        'workspaces.invitations.store',
        'logout',
        'user.destroy',
        'user-profile.edit',
        'user-profile.update',
        'password.edit',
        'password.update',
        'two-factor.show',
        'appearance.edit',
        'verification.notice',
        'verification.send',
        'verification.verify',
        'repos.index',
        'repos.data',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $route = $request->route();

        if ($route !== null && in_array($route->getName(), self::EXCEPTED_ROUTES, true)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        if ($user->workspaces()->count() === 0) {
            return to_route('workspaces.create');
        }

        if ($request->attributes->get('current_workspace') === null) {
            return to_route('workspaces.index');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
