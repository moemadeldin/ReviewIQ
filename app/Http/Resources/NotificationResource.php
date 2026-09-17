<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;

        $readAt = $notification->read_at;
        $data = $notification->data;

        if (isset($data['token']) && is_string($data['token'])) {
            $invitation = WorkspaceInvitation::findByRawToken($data['token']);

            if (! $invitation instanceof WorkspaceInvitation || $invitation->isAccepted() || $invitation->isExpired()) {
                unset($data['token']);
            }
        }

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $data,
            'read_at' => $readAt !== null ? $readAt->toIsoString() : null,
            'created_at' => $notification->created_at->toIsoString(),
        ];
    }
}
