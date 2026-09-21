<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PullRequest;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    /** @var class-string<Review> */
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pull_request_id' => PullRequest::factory(),
            'summary' => fake()->optional()->paragraph(),
            'score_rationale' => fake()->optional()->sentence(),
            'issues' => null,
            'highlights' => null,
            'score' => fake()->numberBetween(1, 10),
            'recommendation' => fake()->randomElement(['approve', 'request_changes', 'comment']),
            'raw_response' => null,
        ];
    }
}
