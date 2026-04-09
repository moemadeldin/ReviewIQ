<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews;

use App\Http\Resources\PullRequestDetailResource;
use App\Http\Resources\PullRequestResource;
use App\Models\PullRequest;
use App\Models\Workspace;
use App\Queries\GetPullRequestsWithReviews;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ReviewController
{
    use APIResponder;

    public function __construct(
        private GetPullRequestsWithReviews $getPullRequests,
    ) {}

    public function index(Workspace $workspace, Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $repositoryId = $request->query('repository_id');
        $status = $request->query('status');

        $paginator = $this->getPullRequests->handle($workspace, $page, $repositoryId, $status);

        return $this->success([
            'pull_requests' => PullRequestResource::collection($paginator)->resolve(),
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
        ], 'ok');
    }

    public function show(PullRequest $pullRequest): JsonResponse
    {
        $pullRequest->load(['repository', 'review']);

        return $this->success([
            'pull_request' => new PullRequestDetailResource($pullRequest)->resolve(),
        ], 'ok');
    }
}
