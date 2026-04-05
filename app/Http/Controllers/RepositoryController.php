<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AttachRepository;
use App\Actions\DeleteRepository;
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

    public function store(Request $request, #[CurrentUser()] User $user, AttachRepository $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $repository = $action->handle($workspace, $user, $fullName);

        return $this->success(['repository' => $repository], 'Repository connected');
    }

    public function destroy(Request $request, #[CurrentUser()] User $user, DeleteRepository $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $action->handle($workspace, $user, $fullName);

        return $this->success(['message' => 'Repository disconnected'], 'ok');
    }
}
