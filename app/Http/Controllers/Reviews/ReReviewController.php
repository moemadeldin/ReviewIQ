<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews;

use App\Actions\ReReviewAction;
use App\Models\PullRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;

final readonly class ReReviewController
{
    public function __invoke(Workspace $workspace, PullRequest $pullRequest, ReReviewAction $action): RedirectResponse
    {
        $action->handle($pullRequest);

        return back();
    }
}
