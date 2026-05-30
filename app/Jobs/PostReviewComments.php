<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\GitHubApi;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Services\GitHubAppAuth;
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

    public function handle(GitHubApi $gitHub, GitHubAppAuth $githubApp): void
    {
        $review = $this->pullRequest->review;

        if (! $review instanceof Review || empty($review->issues)) {
            Log::info('No issues to post as comments for PR #'.$this->pullRequest->number);

            return;
        }

        $repository = $this->pullRequest->repository;
        throw_unless($repository instanceof Repository, RuntimeException::class, 'Repository not found');

        /** @var int $prNumber */
        $prNumber = $this->pullRequest->number;
        /** @var string $repoFullName */
        $repoFullName = $repository->full_name;
        /** @var string $commitSha */
        $commitSha = $this->pullRequest->head_sha;
        /** @var array<int, array{file: string, line: int|null, severity: string, message: string}> $issues */
        $issues = $review->issues;

        $body = sprintf(
            "## ReviewIQ Review — Score: %d/100\n\n%s",
            $review->score ?? 0,
            $review->summary ?? '',
        );

        $gitHub->postReviewComments(
            token: $githubApp->getInstallationToken(),
            fullName: $repoFullName,
            prNumber: $prNumber,
            commitSha: $commitSha,
            issues: $issues,
            body: $body,
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
