<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkspaceInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkspaceInvitation $invitation */
        $invitation = $this->resource;

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at,
            'created_at' => $invitation->created_at,
        ];
    }
}
