<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PullRequest>
 */
final class PullRequestFactory extends Factory
{
    /** @var class-string<PullRequest> */
    protected $model = PullRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'github_pr_id' => fake()->unique()->numberBetween(1, 1000000),
            'title' => fake()->sentence(),
            'number' => fake()->unique()->numberBetween(1, 10000),
            'author' => fake()->userName(),
            'diff_url' => fake()->url(),
            'head_sha' => fake()->sha1(),
            'status' => PullRequestStatus::Pending,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PullRequestStatus::Reviewed,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PullRequestStatus::Pending,
        ]);
    }
}
