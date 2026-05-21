<?php

declare(strict_types=1);
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;

it('returns notifications for authenticated user', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    DatabaseNotification::query()->create([
        'id' => 'test-notification-id',
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Test notification'],
    ]);

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

    $notification = DatabaseNotification::query()->create([
        'id' => 'read-test-notification',
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Test notification'],
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->postJson(route('notifications.mark-read', ['id' => 'read-test-notification']));

    $response->assertOk()
        ->assertJsonPath('data.message', 'Notification marked as read');
});

it('returns 404 for non-existent notification', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->postJson(route('notifications.mark-read', ['id' => 'non-existent-notification']));

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Notification not found');
});

it('marks all notifications as read', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withOwner($user)->create();

    for ($i = 0; $i < 3; $i++) {
        DatabaseNotification::query()->create([
            'id' => 'all-read-test-'.$i,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification'],
        ]);
    }

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->postJson(route('notifications.mark-all-read'));

    $response->assertOk()
        ->assertJsonPath('data.message', 'All notifications marked as read');
});
