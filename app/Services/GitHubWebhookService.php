<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WebhookProvider;
use App\Enums\PullRequestStatus;
use App\Jobs\ProcessPullRequestReview;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class GitHubWebhookService implements WebhookProvider
{
    public function handle(Request $request): void
    {
        $this->verifySignature($request);

        /** @var array{id?: int, action?: string, repository?: array{id?: int}, pull_request?: array{id: int, title: string, number: int, user: array{login: string}, diff_url: string, head: array{sha: string}}} $payload */
        $payload = $request->all();
        $event = $request->header('X-GitHub-Event');
        $action = $payload['action'] ?? null;

        $githubRepoId = (string) ($payload['repository']['id'] ?? '');

        Log::info('Webhook received', [
            'event' => $event,
            'action' => $action,
            'github_repo_id' => $githubRepoId,
        ]);

        if ($event !== 'pull_request' || ! in_array($action, ['opened', 'synchronize'], true)) {
            return;
        }

        $repository = Repository::query()
            ->where('github_repo_id', $githubRepoId)
            ->first();

        if (! $repository) {
            Log::error(sprintf("Repo not found in DB for GitHub ID: %s. Ensure the repo is toggled 'on' in your app.", $githubRepoId));

            return;
        }

        if (! isset($payload['pull_request'])) {
            return;
        }

        /** @var array{id: int, title: string, number: int, user: array{login: string}, diff_url: string, head: array{sha: string}} $prPayload */
        $prPayload = $payload['pull_request'];

        $pr = PullRequest::query()->updateOrCreate(
            ['github_pr_id' => (string) $prPayload['id']],
            [
                'repository_id' => $repository->id,
                'title' => $prPayload['title'],
                'number' => $prPayload['number'],
                'author' => $prPayload['user']['login'],
                'diff_url' => $prPayload['diff_url'],
                'head_sha' => $prPayload['head']['sha'],
            ]
        );

        /** @var PullRequestStatus $status */
        $status = $pr->status;
        if (! in_array($status, [PullRequestStatus::Reviewing, PullRequestStatus::Pending], true)) {
            $pr->update(['status' => PullRequestStatus::Pending]);
            dispatch(new ProcessPullRequestReview($pr));
        }
    }

    private function verifySignature(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');
        /** @var string|null $secret */
        $secret = config('services.github.webhook_secret');

        if (! $signature || ! $secret) {
            Log::error('Webhook verification failed: Missing signature or secret configuration.');
            throw new AccessDeniedHttpException('Missing signature or secret.');
        }

        /** @var string $body */
        $body = $request->getContent();
        $computed = 'sha256='.hash_hmac('sha256', $body, $secret);

        if (! hash_equals($computed, $signature)) {
            Log::error('Webhook verification failed: Invalid signature mismatch.');
            throw new AccessDeniedHttpException('Invalid signature.');
        }
    }
}
