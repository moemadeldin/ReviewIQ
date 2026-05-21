<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PullRequestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read string $id
 * @property-read string $repository_id
 * @property-read int $github_pr_id
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read int|null $number
 * @property-read string|null $author
 * @property-read string|null $diff_url
 * @property-read string|null $head_sha
 * @property-read PullRequestStatus $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class PullRequest extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'repository_id' => 'string',
            'github_pr_id' => 'integer',
            'title' => 'string',
            'description' => 'string',
            'number' => 'integer',
            'author' => 'string',
            'diff_url' => 'string',
            'head_sha' => 'string',
            'status' => PullRequestStatus::class,
        ];
    }
}
