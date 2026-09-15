<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

final readonly class UpdateWorkspace
{
    public function handle(Workspace $workspace, string $name): Workspace
    {
        $workspace->update(['name' => $name]);

        $pattern = sprintf('workspace:role:%s:*', $workspace->id);
        Cache::forget($pattern);

        return $workspace->fresh();
    }
}
