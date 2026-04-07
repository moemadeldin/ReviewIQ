<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Models\Workspace;
use App\Queries\GetWorkspaceMembers;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;

final readonly class GetWorkspaceMembersController
{
    use APIResponder;

    public function __construct(private GetWorkspaceMembers $getWorkspaceMembers) {}

    public function __invoke(
        Workspace $workspace,
    ): JsonResponse {
        $page = (int) request()->query('page', 1);
        $data = $this->getWorkspaceMembers->handle($workspace, $page);

        return $this->success($data, 'ok');
    }
}
