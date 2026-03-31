<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorkspace
{
    public function handle(User $owner, string $name, string $slug): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $slug): Workspace {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'slug' => $slug,
                'owner_id' => $owner->id,
            ]);

            $workspace->addUser($owner, 'owner');

            return $workspace;
        });
    }
}
