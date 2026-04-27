<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property-read string $workspace_id
 * @property-read string $user_id
 * @property-read string $role
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class WorkspaceUser extends Pivot
{
    use HasFactory;

    protected $casts = [
        'workspace_id' => 'string',
        'user_id' => 'string',
        'role' => 'string',
    ];
}
