<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * Shared props are exposed as lazy closures so they are resolved at
     * render time. This is important because the `SetCurrentWorkspace`
     * middleware runs in the `auth` group, which is later in the middleware
     * pipeline than this (`web` group) middleware. Resolving workspace
     * related props lazily guarantees they reflect the workspace that is
     * actually current for the request once `SetCurrentWorkspace` has run.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'turnstileSiteKey' => config('services.turnstile.key'),
            'og' => [
                'url' => config('app.og_image'),
                'width' => config('app.og_image_width'),
                'height' => config('app.og_image_height'),
            ],
            'auth' => [
                'user' => $request->user(...),
                'workspaces' => fn (): array => $request->user()?->workspaces->toArray() ?? [],
                'currentWorkspace' => fn (): ?Workspace => $this->currentWorkspace($request),
                'role' => fn (): ?Roles => $this->currentRole($request, $user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Resolve the workspace that is current for the request.
     */
    private function currentWorkspace(Request $request): ?Workspace
    {
        $workspace = $request->attributes->get('current_workspace');

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        return null;
    }

    /**
     * Resolve the current user's role within the current workspace.
     */
    private function currentRole(Request $request, ?User $user): ?Roles
    {
        $workspace = $request->attributes->get('current_workspace');

        if ($workspace instanceof Workspace && $user instanceof User) {
            return $workspace->roleOf($user);
        }

        return null;
    }
}
