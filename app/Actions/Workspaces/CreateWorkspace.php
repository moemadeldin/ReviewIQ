<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorkspace
{
    public function handle(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name): Workspace {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'owner_id' => $owner->id,
            ]);

            $workspace->addUser($owner, Roles::Owner);

            return $workspace;
        });
    }
}
