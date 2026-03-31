<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
final class WorkspaceFactory extends Factory
{
    /** @var class-string<Workspace> */
    protected $model = Workspace::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            $workspace->users()->syncWithoutDetaching([
                $workspace->owner_id => ['role' => 'owner'],
            ]);
        });
    }

    public function withOwner(User $owner): static
    {
        return $this->state(function (array $attributes) use ($owner): array {
            $slug = 'workspace';
            if (isset($attributes['name']) && is_string($attributes['name'])) {
                $slug = Str::slug($attributes['name']);
            }

            return [
                'owner_id' => $owner->id,
                'slug' => $slug,
            ];
        })->afterCreating(function (Workspace $workspace) use ($owner): void {
            $workspace->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'owner_id' => User::factory(),
        ];
    }
}
