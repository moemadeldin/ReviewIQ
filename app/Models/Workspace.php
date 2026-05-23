<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Roles;
use App\Traits\Sluggable;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string $owner_id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
/**
 * @use HasFactory<WorkspaceFactory>
 */
final class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasUuids;
    use Sluggable;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, WorkspaceUser, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot('role')
            ->using(WorkspaceUser::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * @return HasMany<Repository, $this>
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
        return $this->owner_id === $user->id;
    }

    public function isOwnerOrAdmin(User $user): bool
    {
        $role = $this->roleOf($user);

        return $role instanceof Roles && ($role === Roles::Owner || $role === Roles::Admin);
    }

    public function roleOf(User $user): ?Roles
    {
        $membership = $this->users()->where('user_id', $user->id)->first();

        if ($membership === null) {
            return null;
        }

        /** @var WorkspaceUser $pivot */
        $pivot = $membership->pivot;
        /** @var string|null $roleValue */
        $roleValue = $pivot->role;

        if ($roleValue === '' || $roleValue === null) {
            return null;
        }

        return Roles::from($roleValue);
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
