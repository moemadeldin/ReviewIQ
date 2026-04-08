<?php

declare(strict_types=1);

namespace App\Http\Controllers\Repositories;

use App\Http\Resources\RepositoryResource;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;

final readonly class GetConnectedRepositoriesController
{
    use APIResponder;

    public function __construct(private GetConnectedRepositories $getRepos) {}

    public function __invoke(
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $limit = (int) request()->query('limit', 10);
        $paginator = $this->getRepos->handle($workspace, $page, $limit);

        return $this->success([
            'repositories' => RepositoryResource::collection($paginator),
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
        ], 'ok');
    }
}
