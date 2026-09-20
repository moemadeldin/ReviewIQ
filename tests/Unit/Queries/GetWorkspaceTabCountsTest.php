<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Queries\GetWorkspaceTabCounts;

it('returns counts for reviews, repositories, members, and invitations', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();
    $member = User::factory()->create();
    $workspace->addUser($member, App\Enums\Roles::Member);

    Repository::factory(2)->create(['workspace_id' => $workspace->id]);
    $repo = Repository::factory()->create(['workspace_id' => $workspace->id]);
    PullRequest::factory(3)->create(['repository_id' => $repo->id]);
    WorkspaceInvitation::factory(2)->forWorkspace($workspace)->create();
    WorkspaceInvitation::factory()->forWorkspace($workspace)->accepted()->create();

    $counts = resolve(GetWorkspaceTabCounts::class)->handle($workspace);

    expect($counts)->toBe([
        'reviews' => 3,
        'repositories' => 3,
        'members' => 2,
        'invitations' => 2,
    ]);
});
