<?php

declare(strict_types=1);

namespace App\Http\Requests\Repositories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $workspaceId = $this->query('workspace_id');

        $workspace = null;
        if ($workspaceId) {
            $workspace = Workspace::query()->find($workspaceId);
        }

        if (! $workspace instanceof Workspace) {
            $workspace = $this->attributes->get('current_workspace');
        }

        if (! $workspace instanceof Workspace) {
            return false;
        }

        return $workspace->isOwner($user);
    }
}
