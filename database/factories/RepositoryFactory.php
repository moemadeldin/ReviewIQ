<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
final class RepositoryFactory extends Factory
{
    /** @var class-string<Repository> */
    protected $model = Repository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'github_repo_id' => (string) fake()->unique()->numberBetween(1, 1000000),
            'full_name' => fake()->unique()->company().'/'.fake()->unique()->word(),
            'language' => fake()->randomElement(['PHP', 'JavaScript', 'Python', 'TypeScript', null]),
            'is_active' => true,
            'custom_rules' => null,
            'webhook_id' => null,
        ];
    }
}
