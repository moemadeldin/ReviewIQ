<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\AIReviewer;
use App\Contracts\DiffProvider;
use App\Contracts\GitHubAppAuth;
use App\Enums\PullRequestStatus;
use App\Events\ReviewCompleted;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Review;
use App\Models\Workspace;
use App\Services\DiffLineMapper;
use App\Services\PromptBuilder;
use App\Utilities\Constants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[Tries(Constants::REVIEW_JOB_TRIES)]
#[Timeout(Constants::REVIEW_JOB_TIMEOUT_SECONDS)]
final class ProcessPullRequestReview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PullRequest $pullRequest,
    ) {}

    public function uniqueId(): string
    {
        return 'review-pr-'.$this->pullRequest->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return Constants::REVIEW_JOB_BACKOFF_SECONDS;
    }

    public function handle(
        DiffProvider $diffService,
        PromptBuilder $promptBuilder,
        DiffLineMapper $diffLineMapper,
        AIReviewer $aiReviewer,
        GitHubAppAuth $githubApp,
    ): void {
        $updated = PullRequest::query()
            ->where('id', $this->pullRequest->id)
            ->where('status', PullRequestStatus::Pending)
            ->update(['status' => PullRequestStatus::Reviewing]);

        if (! $updated) {
            Log::info('PR #'.$this->pullRequest->number.' not pending, skipping');

            return;
        }

        $this->pullRequest->refresh();

        try {
            $this->review($diffService, $promptBuilder, $diffLineMapper, $aiReviewer, $githubApp);
        } catch (Throwable $throwable) {
            $this->pullRequest->update(['status' => PullRequestStatus::Pending]);

            throw $throwable;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Review job failed for PR #'.$this->pullRequest->number, [
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $maxTries = $this->job?->maxTries() ?? Constants::REVIEW_JOB_TRIES;

        $status = $this->attempts() >= $maxTries
            ? PullRequestStatus::Failed
            : PullRequestStatus::Pending;

        $this->pullRequest->update([
            'status' => $status,
            'pending_head_sha' => null,
        ]);
    }

    private function review(
        DiffProvider $diffService,
        PromptBuilder $promptBuilder,
        DiffLineMapper $diffLineMapper,
        AIReviewer $aiReviewer,
        GitHubAppAuth $githubApp,
    ): void {
        $repository = $this->pullRequest->repository;
        throw_unless($repository instanceof Repository, RuntimeException::class, 'Repository not found');

        $workspace = $repository->workspace;
        throw_unless($workspace instanceof Workspace, RuntimeException::class, 'Workspace not found');

        $prNumber = $this->pullRequest->number;
        throw_unless(is_int($prNumber), RuntimeException::class, 'Invalid PR number');

        $repoFullName = $repository->full_name;
        throw_unless($repoFullName !== '', RuntimeException::class, 'Invalid repository full name');

        // Use pending_head_sha if set (push during review), otherwise use head_sha
        $headSha = $this->pullRequest->pending_head_sha ?? $this->pullRequest->head_sha ?? '';

        $diff = $diffService->getDiff(
            token: $githubApp->getInstallationToken(),
            repoFullName: $repoFullName,
            prNumber: $prNumber,
            headSha: $headSha,
        );

        // Annotate diff with line numbers for the LLM
        $annotatedDiff = $diffLineMapper->annotate($diff);

        // Truncate diff if too large
        $truncatedDiff = $promptBuilder->truncateDiff($annotatedDiff);

        Log::info('Diff fetched for PR #'.$this->pullRequest->number, [
            'repo' => $repoFullName,
            'preview' => mb_substr($diff, 0, 120),
            'head_sha' => $headSha,
            'original_length' => mb_strlen($diff),
            'truncated_length' => mb_strlen($truncatedDiff),
        ]);

        /** @var array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, array{}>, highlights?: array<int, string>, recommendation?: string} $reviewResult */
        $reviewResult = $aiReviewer->review(
            systemPrompt: $promptBuilder->buildSystemPrompt(),
            userPrompt: $promptBuilder->buildUserPrompt(
                diff: $truncatedDiff,
                prTitle: $this->pullRequest->title ?? '',
                prDescription: $this->pullRequest->description,
                repoLanguage: $repository->language,
                customRules: $repository->custom_rules,
            ),
        );

        Review::query()->updateOrCreate(
            ['pull_request_id' => $this->pullRequest->id],
            [
                'summary' => $reviewResult['summary'] ?? '',
                'score' => $reviewResult['score'] ?? 0,
                'score_rationale' => $reviewResult['score_rationale'] ?? '',
                'issues' => $this->validateIssues($diffLineMapper, $diff, $reviewResult['issues'] ?? []),
                'highlights' => $reviewResult['highlights'] ?? [],
                'recommendation' => $reviewResult['recommendation'] ?? 'comment',
                'raw_response' => json_encode($reviewResult),
            ],
        );

        event(new ReviewCompleted(
            prId: $this->pullRequest->id,
            review: $reviewResult,
        ));

        dispatch(new PostReviewComments($this->pullRequest));

        // Check if a new push arrived during review
        $this->pullRequest->refresh();
        if ($this->pullRequest->pending_head_sha !== null
            && $this->pullRequest->pending_head_sha !== $this->pullRequest->head_sha) {
            $newHeadSha = $this->pullRequest->pending_head_sha;
            $this->pullRequest->update([
                'head_sha' => $newHeadSha,
                'pending_head_sha' => null,
                'status' => PullRequestStatus::Pending,
            ]);
            Log::info('New push detected during review, re-dispatching', [
                'pr' => $this->pullRequest->number,
                'new_head_sha' => $newHeadSha,
            ]);
            dispatch(new self($this->pullRequest->fresh()));

            return;
        }

        $this->pullRequest->update([
            'status' => PullRequestStatus::Reviewed,
            'pending_head_sha' => null,
        ]);

        Log::info('Review stored for PR #'.$this->pullRequest->number, [
            'score' => $reviewResult['score'] ?? 0,
        ]);
    }

    /**
     * Validate issues against the diff line map.
     * Invalid lines are set to null (kept in summary instead of inline).
     *
     * @param  array<int, array{file: string, line: int|null, severity: string, description: string, category: string, suggestion: string}>  $issues
     * @return array<int, array{file: string, line: int|null, severity: string, description: string, category: string, suggestion: string}>
     */
    private function validateIssues(DiffLineMapper $diffLineMapper, string $originalDiff, array $issues): array
    {
        $map = $diffLineMapper->map($originalDiff);
        $validated = [];

        foreach ($issues as $issue) {
            $file = $issue['file'] ?? '';
            $line = $issue['line'] ?? null;

            $validation = $diffLineMapper->validateIssue($map, $file, $line);

            $validated[] = [
                'file' => $validation['file'],
                'line' => $validation['line'],
                'severity' => $issue['severity'] ?? 'medium',
                'description' => $issue['description'] ?? '',
                'category' => $issue['category'] ?? 'maintainability',
                'suggestion' => $issue['suggestion'] ?? '',
            ];

            if (! $validation['valid'] && $line !== null) {
                Log::warning('Issue line invalid, moved to summary', [
                    'file' => $file,
                    'original_line' => $line,
                    'valid_lines' => array_keys($map[$diffLineMapper->findFileKey($map, $file)] ?? []),
                ]);
            }
        }

        return $validated;
    }
}
