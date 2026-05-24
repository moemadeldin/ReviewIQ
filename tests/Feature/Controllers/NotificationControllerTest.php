<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

function createNotification(User $user): Notification
{
    return Notification::query()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Test notification'],
    ]);
}

it('returns notifications for authenticated user', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    createNotification($user);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'notifications',
                'current_page',
                'has_more',
                'unread_count',
            ],
        ])
        ->assertJsonPath('data.unread_count', 1);
});

it('marks notification as read', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $notification = createNotification($user);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->patchJson(route('notifications.mark-read', ['notification' => $notification->id]));

    $response->assertOk()
        ->assertJsonPath('data.message', 'Notification marked as read');
});

it('returns 404 for non-existent notification', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->patchJson(route('notifications.mark-read', ['notification' => '00000000-0000-7000-8000-000000000000']));

    $response->assertStatus(404);
});

it('marks all notifications as read', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    for ($i = 0; $i < 3; $i++) {
        createNotification($user);
    }

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->patchJson(route('notifications.mark-all-read'));

    $response->assertOk()
        ->assertJsonPath('data.message', 'All notifications marked as read');
});
