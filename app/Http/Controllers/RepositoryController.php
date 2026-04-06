<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AttachRepository;
use App\Actions\DeleteRepository;
use App\Http\Requests\AttachRepositoryRequest;
use App\Http\Requests\DeleteRepositoryRequest;
use App\Http\Requests\ToggleRepositoryRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetRepositoriesData;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RepositoryController
{
    use APIResponder;

    public function __construct(
        private GetRepositoriesData $getRepositoriesData,
    ) {}

    public function index(Request $request, #[CurrentUser()] User $user): JsonResponse
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->success(['repositories' => [], 'connected_repos' => [], 'has_more' => false], 'ok');
        }

        $page = (int) $request->query('page', 1);
        $data = $this->getRepositoriesData->handle($user, $workspace, $page);

        return $this->success($data, 'ok');
    }

    public function connected(Request $request, #[CurrentUser()] User $user, string $workspace): JsonResponse
    {
        $workspaceModel = Workspace::query()->where('slug', $workspace)->first();

        if (! $workspaceModel) {
            return $this->fail('Workspace not found', Response::HTTP_NOT_FOUND);
        }

        $page = (int) $request->query('page', 1);
        $limit = 10;

        $repos = $workspaceModel->repositories()
            ->latest('repositories.created_at')
            ->simplePaginate($limit, page: $page);

        $items = $repos->getCollection()->map(fn ($repo): array => [
            'id' => $repo->id,
            'full_name' => $repo->full_name,
            'language' => $repo->language,
            'is_active' => $repo->is_active,
            'webhook_id' => $repo->webhook_id,
            'connected_at' => $repo->pivot->created_at,
        ]);

        return $this->success([
            'repositories' => $items,
            'current_page' => $repos->currentPage(),
            'has_more' => $repos->hasMorePages(),
        ], 'ok');
    }

    public function store(AttachRepositoryRequest $request, #[CurrentUser()] User $user, AttachRepository $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $repository = $action->handle($workspace, $user, $fullName);

        return $this->success(['repository' => $repository], 'Repository connected');
    }

    public function destroy(DeleteRepositoryRequest $request, #[CurrentUser()] User $user, DeleteRepository $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $action->handle($workspace, $user, $fullName);

        return $this->success(['message' => 'Repository disconnected'], 'ok');
    }

    public function toggle(ToggleRepositoryRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validated();

        $repo = $workspace->repositories()->where('repositories.id', $validated['repo_id'])->first();

        if (! $repo) {
            return $this->fail('Repository not found', Response::HTTP_NOT_FOUND);
        }

        $workspace->repositories()->updateExistingPivot($repo->id, [
            'is_active' => $validated['is_active'],
        ]);

        return $this->success(['message' => 'Repository toggled'], 'ok');
    }
}
