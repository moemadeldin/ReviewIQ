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

        $payload = $request->all();
        $event = $request->header('X-GitHub-Event');
        $action = $payload['action'] ?? null;

        $githubRepoId = (string) ($payload['repository']['id'] ?? '');

        Log::info('Webhook received', [
            'event' => $event,
            'action' => $action,
            'github_repo_id' => $githubRepoId,
        ]);

        if ($event !== 'pull_request' || ! in_array($action, ['opened', 'synchronize'])) {
            return;
        }

        $repository = Repository::query()
            ->where('github_repo_id', $githubRepoId)
            ->first();

        if (! $repository) {
            Log::error(sprintf("Repo not found in DB for GitHub ID: %s. Ensure the repo is toggled 'on' in your app.", $githubRepoId));

            return;
        }

        $pr = PullRequest::query()->updateOrCreate(
            ['github_pr_id' => (string) $payload['pull_request']['id']],
            [
                'repository_id' => $repository->id,
                'title' => $payload['pull_request']['title'],
                'number' => $payload['pull_request']['number'],
                'author' => $payload['pull_request']['user']['login'],
                'diff_url' => $payload['pull_request']['diff_url'],
                'head_sha' => $payload['pull_request']['head']['sha'],
            ]
        );

        Log::info('PR Saved Successfully', ['pr_id' => $pr->id, 'number' => $pr->number, 'status' => $pr->status->value]);

        if (! in_array($pr->status, [PullRequestStatus::Reviewing, PullRequestStatus::Pending])) {
            $pr->update(['status' => PullRequestStatus::Pending]);
            dispatch(new ProcessPullRequestReview($pr));
        }
    }

    private function verifySignature(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('services.github.webhook_secret');

        if (! $signature || ! $secret) {
            Log::error('Webhook verification failed: Missing signature or secret configuration.');
            throw new AccessDeniedHttpException('Missing signature or secret.');
        }

        $computed = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (! hash_equals($computed, $signature)) {
            Log::error('Webhook verification failed: Invalid signature mismatch.');
            throw new AccessDeniedHttpException('Invalid signature.');
        }
    }
}
