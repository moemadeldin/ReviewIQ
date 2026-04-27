<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Repository $repo */
        $repo = $this->resource;

        return [
            'id' => $repo->id,
            'full_name' => $repo->full_name,
            'language' => $repo->language,
            'is_active' => $repo->is_active,
            'webhook_id' => $repo->webhook_id,
            'connected_at' => $repo->created_at,
        ];
    }
}
