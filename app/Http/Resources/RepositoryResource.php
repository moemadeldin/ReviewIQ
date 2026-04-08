<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RepositoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'language' => $this->language,
            'is_active' => $this->is_active,
            'webhook_id' => $this->webhook_id,
            'connected_at' => $this->created_at,
        ];
    }
}
