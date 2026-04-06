<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class WorkspaceInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Str::random(64),
            'role' => $this->faker->randomElement(),
            'expires_at' => now()->addHours(48),
            'accepted_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
        ]);
    }
}
