<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class DeleteWorkspaceMember
{
    public function handle(Workspace $workspace, User $currentUser, User $memberToRemove): void
    {
        throw_unless(
            $workspace->isOwner($currentUser),
            HttpException::class,
            Response::HTTP_FORBIDDEN,
            'Only the workspace owner can remove members',
        );

        throw_if(
            $workspace->isOwner($memberToRemove),
            HttpException::class,
            Response::HTTP_FORBIDDEN,
            'Cannot remove the workspace owner',
        );

        $workspace->users()->detach($memberToRemove->id);
        Cache::forget(sprintf('workspace:role:%s:%s', $workspace->id, $memberToRemove->id));
    }
}
