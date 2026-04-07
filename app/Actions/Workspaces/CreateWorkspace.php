<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorkspace
{
    public function handle(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name): Workspace {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'owner_id' => $user->id,
            ]);

            $workspace->addUser($user, Roles::Owner);

            return $workspace;
        });
    }
}
