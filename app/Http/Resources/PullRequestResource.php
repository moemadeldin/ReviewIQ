<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PullRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PullRequest $pr */
        $pr = $this->resource;
        $repository = $pr->repository;

        return [
            'id' => $pr->id,
            'title' => $pr->title,
            'number' => $pr->number,
            'author' => $pr->author,
            'diff_url' => $pr->diff_url,
            'head_sha' => $pr->head_sha,
            'status' => $pr->status->value,
            'created_at' => $pr->created_at,
            'repository' => $repository instanceof Repository ? [
                'id' => $repository->id,
                'full_name' => $repository->full_name,
            ] : null,
            'review' => $pr->review instanceof Review ? $pr->review->toArray() : null,
        ];
    }
}
