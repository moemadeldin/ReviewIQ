<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Queries\GetConnectedRepositories;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class GetConnectedRepositoriesController
{
    use APIResponder;

    public function __construct(private GetConnectedRepositories $getRepos) {}

    public function __invoke(
        #[CurrentUser()] User $user,
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $data = $this->getRepos->handle($workspace, $page);

        return $this->success($data, 'ok');
    }
}
