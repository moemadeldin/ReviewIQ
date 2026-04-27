<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Models\WorkspaceUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkspaceMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        /** @var mixed $role */
        $role = null;
        if ($user->relationLoaded('pivot')) {
            $pivot = $user->getRelation('pivot');
            if ($pivot instanceof WorkspaceUser) {
                $role = $pivot->role;
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->github_avatar,
            'role' => $role,
        ];
    }
}
