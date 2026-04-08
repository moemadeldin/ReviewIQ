<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $pull_request_id
 * @property-read string|null $summary
 * @property-read string|null $score_rationale
 * @property-read array|null $issues
 * @property-read array|null $highlights
 * @property-read int|null $score
 * @property-read string|null $recommendation
 * @property-read string|null $raw_response
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Review extends Model
{
    use HasFactory;
    use HasUuids;

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'pull_request_id' => 'string',
            'summary' => 'string',
            'score_rationale' => 'string',
            'issues' => 'array',
            'highlights' => 'array',
            'score' => 'integer',
            'recommendation' => 'string',
            'raw_response' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
