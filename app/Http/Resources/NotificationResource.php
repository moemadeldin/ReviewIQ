<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
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

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $readAt !== null ? $readAt->toIsoString() : null,
            'created_at' => $notification->created_at->toIsoString(),
        ];
    }
}
