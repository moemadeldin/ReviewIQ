<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Roles;
use App\Traits\Sluggable;
use Carbon\CarbonInterface;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string $owner_id
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasUuids;
    use Sluggable;

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, Workspace>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, Workspace, Pivot>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Repository>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function addUser(User $user, Roles $role): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function isOwner(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        $membership = $this->users()->where('user_id', $user->id)->first();

        if (! $membership) {
            return false;
        }

        return $membership->pivot->role === 'owner';
    }

    public function isAdmin(User $user): bool
    {
        $membership = $this->users()->where('user_id', $user->id)->first();

        if (! $membership) {
            return false;
        }

        return $membership->pivot->role === 'admin';
    }

    public function isOwnerOrAdmin(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return $this->isAdmin($user);
    }

    public function roleOf(User $user): ?string
    {
        $membership = $this->users()->where('user_id', $user->id)->first();

        if ($membership === null) {
            return null;
        }

        $pivot = $membership->pivot;

        return (string) $pivot->role;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'slug' => 'string',
            'owner_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
