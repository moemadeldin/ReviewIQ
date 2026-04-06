<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $workspace_id
 * @property-read string $github_repo_id
 * @property-read string $full_name
 * @property-read string|null $language
 * @property-read bool $is_active
 * @property-read string|null $custom_rules
 * @property-read string|null $webhook_id
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Workspace, Repository>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'workspace_id' => 'string',
            'github_repo_id' => 'string',
            'full_name' => 'string',
            'language' => 'string',
            'is_active' => 'boolean',
            'custom_rules' => 'string',
            'webhook_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
