<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\GitHubApi;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[Tries(3)]
final class PostReviewComments implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PullRequest $pullRequest,
    ) {}

    public function handle(GitHubApi $gitHub): void
    {
        $review = $this->pullRequest->review;

        if (! $review instanceof Review || empty($review->issues)) {
            Log::info('No issues to post as comments for PR #'.$this->pullRequest->number);

            return;
        }

        $repository = $this->pullRequest->repository;
        throw_unless($repository instanceof Repository, RuntimeException::class, 'Repository not found');

        $workspace = $repository->workspace;
        throw_unless($workspace instanceof Workspace, RuntimeException::class, 'Workspace not found');

        $owner = $workspace->owner;
        throw_unless($owner instanceof User, RuntimeException::class, 'Workspace owner not found');

        /** @var int $prNumber */
        $prNumber = $this->pullRequest->number;
        /** @var string $repoFullName */
        $repoFullName = $repository->full_name;
        /** @var string $token */
        $token = $owner->github_token;
        /** @var string $commitSha */
        $commitSha = $this->pullRequest->head_sha;
        /** @var array<int, array{file: string, line: int|null, severity: string, message: string}> $issues */
        $issues = $review->issues;

        $gitHub->postReviewComments(
            token: $token,
            fullName: $repoFullName,
            prNumber: $prNumber,
            commitSha: $commitSha,
            issues: $issues,
        );

        Log::info('Posted '.count($issues).' review comments for PR #'.$this->pullRequest->number, [
            'repo' => $repoFullName,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Failed to post review comments for PR #'.$this->pullRequest->number, [
            'error' => $e->getMessage(),
        ]);
    }
}
