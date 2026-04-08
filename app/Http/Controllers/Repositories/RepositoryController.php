<?php

declare(strict_types=1);

namespace App\Http\Controllers\Repositories;

use App\Actions\Repositories\AttachRepository;
use App\Actions\Repositories\DeleteRepository;
use App\Http\Requests\Workspaces\WorkspaceOwnerRequest;
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

    public function index(#[CurrentUser()] User $user, Workspace $workspace, Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $data = $this->getRepositoriesData->handle($user, $workspace, $page);

        return $this->success($data, 'ok');
    }

    public function store(WorkspaceOwnerRequest $request, #[CurrentUser()] User $user, Workspace $workspace, AttachRepository $action, string $fullName): JsonResponse|Response
    {
        $repository = $action->handle($workspace, $user, $fullName);

        return $this->success(['repository' => $repository], 'Repository connected');
    }

    public function destroy(WorkspaceOwnerRequest $request, #[CurrentUser()] User $user, Workspace $workspace, DeleteRepository $action, string $fullName): JsonResponse|Response
    {

        $action->handle($workspace, $user, $fullName);

        return $this->success(['message' => 'Repository disconnected'], 'ok');
    }
}
