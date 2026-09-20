<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WebhookProvider;
use App\Enums\PullRequestAction;
use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class GitHubWebhookService implements WebhookProvider
{
    public function handle(Request $request): void
    {
        $deliveryId = $request->header('X-GitHub-Delivery');
        if ($deliveryId && Cache::has('github:webhook:'.$deliveryId)) {
            Log::info('Duplicate webhook delivery ignored', ['delivery_id' => $deliveryId]);

            return;
        }

        /** @var array{id?: int, action?: string, repository?: array{id?: int}, pull_request?: array{id: int, title: string, number: int, user: array{login: string}, diff_url: string, head: array{sha: string}, draft: bool}} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $event = $request->header('X-GitHub-Event');
        $action = $payload['action'] ?? null;

        $githubRepoId = (string) ($payload['repository']['id'] ?? '');

        Log::info('Webhook received', [
            'event' => $event,
            'action' => $action,
            'github_repo_id' => $githubRepoId,
            'delivery_id' => $deliveryId,
        ]);

        if ($event !== 'pull_request' || ! $this->isActionHandled($action)) {
            return;
        }

        if (! isset($payload['pull_request'])) {
            return;
        }

        $repository = Repository::query()
            ->where('github_repo_id', $githubRepoId)
            ->first();

        if (! $repository) {
            Log::info(sprintf('Repo not found in DB for GitHub ID: %s', $githubRepoId));

            return;
        }

        /** @var array{id: int, title: string, number: int, user: array{login: string}, diff_url: string, head: array{sha: string}, body: string|null, draft: bool} $prPayload */
        $prPayload = $payload['pull_request'];

        if ($this->shouldSkipPr($prPayload)) {
            Log::info('Skipping PR review', [
                'pr' => $prPayload['number'],
                'author' => $prPayload['user']['login'],
                'draft' => $prPayload['draft'] ?? false,
            ]);

            return;
        }

        $headSha = $prPayload['head']['sha'];
        $pr = PullRequest::query()->updateOrCreate(
            ['github_pr_id' => (string) $prPayload['id']],
            [
                'repository_id' => $repository->id,
                'title' => $prPayload['title'],
                'number' => $prPayload['number'],
                'author' => $prPayload['user']['login'],
                'diff_url' => $prPayload['diff_url'],
                'head_sha' => $headSha,
                'description' => $prPayload['body'] ?? null,
            ]
        );

        // Dispatch review job if:
        // 1. PR was just created (new PR), OR
        // 2. PR exists but was not in Reviewing/Pending state
        $shouldDispatch = $pr->wasRecentlyCreated
            || ! in_array($pr->status, [PullRequestStatus::Reviewing, PullRequestStatus::Pending], true);

        if ($shouldDispatch) {
            $pr->update(['status' => PullRequestStatus::Pending, 'head_sha' => $headSha]);
            dispatch(new ProcessPullRequestReview($pr->fresh()));
        } elseif ($pr->head_sha !== $headSha) {
            // PR is already being reviewed - check if head_sha changed
            $pr->update(['head_sha' => $headSha, 'pending_head_sha' => $headSha]);
        }

        if ($deliveryId) {
            Cache::put('github:webhook:'.$deliveryId, true, 86400); // 24h TTL
        }
    }

    private function isActionHandled(?string $action): bool
    {
        $configuredActions = array_merge(
            [PullRequestAction::Opened->value, PullRequestAction::Synchronize->value],
            config('github.webhook_extra_actions', ['reopened', 'ready_for_review'])
        );

        return in_array($action, $configuredActions, true);
    }

    private function shouldSkipPr(array $prPayload): bool
    {
        if (($prPayload['draft'] ?? false) === true) {
            return true;
        }

        $author = $prPayload['user']['login'] ?? '';
        $skipBots = config('github.webhook_skip_bots', ['dependabot[bot]', 'renovate[bot]']);

        return in_array($author, $skipBots, true);
    }
}
