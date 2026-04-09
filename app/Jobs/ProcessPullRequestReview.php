<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\AIReviewer;
use App\Events\ReviewChunkReceived;
use App\Events\ReviewCompleted;
use App\Models\PullRequest;
use App\Models\Review;
use App\Services\GitHubDiffService;
use App\Services\PromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ProcessPullRequestReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly PullRequest $pullRequest,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        GitHubDiffService $diffService,
        PromptBuilder $promptBuilder,
        AIReviewer $aiReviewer,
    ): void {
        if (in_array($this->pullRequest->status, ['reviewing', 'reviewed'])) {
            Log::info('PR #'.$this->pullRequest->number.' already processed, skipping');

            return;
        }

        $repo = $this->pullRequest->repository;
        $owner = $repo->workspace->owner;

        throw_unless(
            $owner?->github_token,
            RuntimeException::class,
            sprintf('PR #%s: workspace owner has no GitHub token.', $this->pullRequest->number)
        );

        $this->pullRequest->update(['status' => 'reviewing']);

        $diff = $diffService->getDiff(
            token: $owner->github_token,
            repoFullName: $repo->full_name,
            prNumber: $this->pullRequest->number,
        );

        Log::info('Diff fetched for PR #'.$this->pullRequest->number, [
            'repo' => $repo->full_name,
            'preview' => mb_substr($diff, 0, 120),
        ]);

        $review = $aiReviewer->stream(
            systemPrompt: $promptBuilder->buildSystemPrompt(),
            userPrompt: $promptBuilder->buildUserPrompt(
                diff: $diff,
                prTitle: $this->pullRequest->title,
                prDescription: $this->pullRequest->description ?? null,
                repoLanguage: $repo->language,
                customRules: $repo->custom_rules,
            ),
            onChunk: fn (string $chunk) => event(new ReviewChunkReceived(
                prId: $this->pullRequest->id,
                chunk: $chunk,
            )),
        );

        Review::query()->create([
            'pull_request_id' => $this->pullRequest->id,
            'summary' => $review['summary'],
            'score' => $review['score'],
            'score_rationale' => $review['score_rationale'],
            'issues' => $review['issues'],
            'highlights' => $review['highlights'],
            'recommendation' => $review['recommendation'],
            'raw_response' => json_encode($review),
        ]);

        $this->pullRequest->update(['status' => 'reviewed']);

        event(new ReviewCompleted(
            prId: $this->pullRequest->id,
            review: $review,
        ));

        Log::info('Review stored for PR #'.$this->pullRequest->number, [
            'score' => $review['score'],
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Review job failed for PR #'.$this->pullRequest->number, [
            'error' => $e->getMessage(),
        ]);

        $this->pullRequest->update(['status' => 'failed']);
    }
}
