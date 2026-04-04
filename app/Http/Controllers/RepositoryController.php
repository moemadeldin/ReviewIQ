<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteRepositoryAction;
use App\Actions\StoreRepositoryAction;
use App\Models\Workspace;
use App\Queries\GetRepositoriesData;
use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RepositoryController
{
    use APIResponses;

    public function __construct(
        private GetRepositoriesData $getRepositoriesData,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('currentWorkspace');

        if (! $workspace instanceof Workspace) {
            return $this->success(['repositories' => [], 'connected_repos' => []], 'ok');
        }

        $data = $this->getRepositoriesData->handle($workspace);

        return $this->success($data, 'ok');
    }

    public function store(Request $request, StoreRepositoryAction $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('currentWorkspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $repository = $action->handle($workspace, $fullName);

        return $this->success(['repository' => $repository], 'Repository connected');
    }

    public function destroy(Request $request, DeleteRepositoryAction $action, string $fullName): JsonResponse|Response
    {
        $workspace = $request->attributes->get('currentWorkspace');

        if (! $workspace instanceof Workspace) {
            return $this->fail('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $action->handle($workspace, $fullName);

        return $this->success(['message' => 'Repository disconnected'], 'ok');
    }
}
