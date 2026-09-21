<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read string $id
 * @property-read string $pull_request_id
 * @property-read string|null $previous_review_id
 * @property-read Review|null $previousReview
 * @property-read Review|null $nextReview
 * @property-read string|null $summary
 * @property-read string|null $score_rationale
 * @property-read array<int, array{severity: string, file: string, line: int|null, title: string, description: string, suggestion: string}>|null $issues
 * @property-read array<int, string>|null $highlights
 * @property-read int|null $score
 * @property-read string|null $recommendation
 * @property-read string|null $raw_response
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function previousReview(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_review_id');
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function nextReview(): HasOne
    {
        return $this->hasOne(self::class, 'previous_review_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'pull_request_id' => 'string',
            'previous_review_id' => 'string',
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
