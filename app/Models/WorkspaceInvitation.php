<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Roles;
use Carbon\CarbonInterface;
use Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $workspace_id
 * @property-read string $email
 * @property-read string $token
 * @property-read Roles $role
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface|null $accepted_at
 * @property-read CarbonInterface $created_at
 */
final class WorkspaceInvitation extends Model
{
    /** @use HasFactory<WorkspaceInvitationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Workspace, WorkspaceInvitation>
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
            'email' => 'string',
            'token' => 'string',
            'role' => Roles::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
