<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class DeleteWorkspace
{
    public function handle(Workspace $workspace, User $user): void
    {
        throw_unless(
            $workspace->isOwner($user),
            HttpException::class,
            Response::HTTP_FORBIDDEN,
            'Only the workspace owner can delete the workspace',
        );

        $workspace->delete();
    }
}
