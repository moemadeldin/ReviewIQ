<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'github_token' => 'fake-token',
    ]);
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user, ['role' => 'admin']);
});

it('returns empty data when user has workspace but none selected in session', function (): void {
    Http::fake([
        'https://api.github.com/*' => Http::response([], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('repos.data'));

    $response->assertOk()
        ->assertJsonPath('data.repositories', [])
        ->assertJsonPath('data.connected_repos', [])
        ->assertJsonPath('data.has_more', false);
});

it('stores a repository successfully', function (): void {
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            ['id' => 123, 'full_name' => 'owner/repo', 'language' => 'PHP'],
        ], 200),
        'https://api.github.com/repos/*/hooks' => Http::response(['id' => 999], 201),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('repos.store', ['fullName' => 'owner/repo']));

    $response->assertJsonPath('status', 'Success')
        ->assertJsonPath('message', 'Repository connected')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'repository' => [
                    'id',
                    'workspace_id',
                    'full_name',
                    'is_active',
                ],
            ],
        ]);
});

it('redirects to workspaces when user has no workspace on store', function (): void {
    $otherUser = User::factory()->create([
        'github_token' => 'other-token',
    ]);

    $response = $this->actingAs($otherUser)
        ->post(route('repos.store', ['fullName' => 'owner/repo']));

    $response->assertRedirectToRoute('workspaces.create');
});

it('redirects to workspaces when user has no workspace on destroy', function (): void {
    $otherUser = User::factory()->create([
        'github_token' => 'other-token',
    ]);

    $response = $this->actingAs($otherUser)
        ->delete(route('repos.destroy', ['fullName' => 'owner/repo']));

    $response->assertRedirectToRoute('workspaces.create');
});

it('destroys a repository successfully', function (): void {
    $repo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => 'owner/repo',
        'is_active' => true,
        'webhook_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->delete(route('repos.destroy', ['fullName' => 'owner/repo']));

    $response->assertOk();

    expect($repo->fresh()->is_active)->toBeFalse();
});

it('redirects to workspaces.create when user has no workspaces on destroy', function (): void {
    $userWithoutWorkspace = User::factory()->create();

    $response = $this->actingAs($userWithoutWorkspace)
        ->delete(route('repos.destroy', ['fullName' => 'owner/repo']));

    $response->assertRedirectToRoute('workspaces.create');
});
