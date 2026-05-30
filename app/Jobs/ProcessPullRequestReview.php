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
use App\Services\PromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[Tries(3)]
#[Timeout(120)]
final class ProcessPullRequestReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PullRequest $pullRequest,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        DiffProvider $diffService,
        PromptBuilder $promptBuilder,
        AIReviewer $aiReviewer,
        GitHubAppAuth $githubApp,
    ): void {
        $updated = PullRequest::query()
            ->where('id', $this->pullRequest->id)
            ->where('status', PullRequestStatus::Pending)
            ->update(['status' => PullRequestStatus::Reviewing]);

        if (! $updated) {
            Log::info('PR #'.$this->pullRequest->number.' already processing or reviewed, skipping');

            return;
        }

        $this->pullRequest->refresh();

        $repository = $this->pullRequest->repository;
        throw_unless($repository instanceof Repository, RuntimeException::class, 'Repository not found');

        $workspace = $repository->workspace;
        throw_unless($workspace instanceof Workspace, RuntimeException::class, 'Workspace not found');

        $prNumber = $this->pullRequest->number;
        throw_unless(is_int($prNumber), RuntimeException::class, 'Invalid PR number');

        $repoFullName = $repository->full_name;
        throw_unless($repoFullName !== '', RuntimeException::class, 'Invalid repository full name');

        $diff = $diffService->getDiff(
            token: $githubApp->getInstallationToken(),
            repoFullName: $repoFullName,
            prNumber: $prNumber,
        );

        Log::info('Diff fetched for PR #'.$this->pullRequest->number, [
            'repo' => $repoFullName,
            'preview' => mb_substr($diff, 0, 120),
        ]);

        /** @var array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, array{}>, highlights?: array<int, string>, recommendation?: string} $reviewResult */
        $reviewResult = $aiReviewer->review(
            systemPrompt: $promptBuilder->buildSystemPrompt(),
            userPrompt: $promptBuilder->buildUserPrompt(
                diff: $diff,
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
                'issues' => $reviewResult['issues'] ?? [],
                'highlights' => $reviewResult['highlights'] ?? [],
                'recommendation' => $reviewResult['recommendation'] ?? 'comment',
                'raw_response' => json_encode($reviewResult),
            ],
        );

        $this->pullRequest->update(['status' => PullRequestStatus::Reviewed]);

        event(new ReviewCompleted(
            prId: $this->pullRequest->id,
            review: $reviewResult,
        ));

        dispatch(new PostReviewComments($this->pullRequest));

        Log::info('Review stored for PR #'.$this->pullRequest->number, [
            'score' => $reviewResult['score'] ?? 0,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Review job failed for PR #'.$this->pullRequest->number, [
            'error' => $e->getMessage(),
        ]);

        $this->pullRequest->update(['status' => PullRequestStatus::Pending]);
    }
}
