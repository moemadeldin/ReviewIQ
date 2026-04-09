<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PullRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'number' => $this->number,
            'author' => $this->author,
            'diff_url' => $this->diff_url,
            'head_sha' => $this->head_sha,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'repository' => $this->whenLoaded('repository', fn (): array => [
                'id' => $this->repository->id,
                'full_name' => $this->repository->full_name,
            ]),
            'review' => $this->whenLoaded('review', fn (): ?array => $this->review?->toArray()),
        ];
    }
}
