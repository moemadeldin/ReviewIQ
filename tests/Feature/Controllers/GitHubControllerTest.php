<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GitHubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

function createMockSocialiteUser(string $id, string $name, string $nickname, string $email, string $avatar, string $token): SocialiteUser
{
    $mock = Mockery::mock(SocialiteUser::class);
    $mock->shouldReceive('getId')->andReturn($id);
    $mock->id = $id;
    $mock->shouldReceive('getName')->andReturn($name);
    $mock->name = $name;
    $mock->shouldReceive('getNickname')->andReturn($nickname);
    $mock->nickname = $nickname;
    $mock->shouldReceive('getEmail')->andReturn($email);
    $mock->email = $email;
    $mock->shouldReceive('getAvatar')->andReturn($avatar);
    $mock->avatar = $avatar;
    $mock->token = $token;

    return $mock;
}

function createMockGitHubDriver(SocialiteUser $user): GitHubProvider
{
    $mock = Mockery::mock(GitHubProvider::class);
    $mock->shouldReceive('user')->andReturn($user);

    return $mock;
}

it('redirects to github oauth', function (): void {
    $response = $this->get(route('auth.github'));

    $response->assertRedirectContains('github.com/login/oauth/authorize');
});

it('creates user from github oauth', function (): void {
    Http::preventStrayRequests();

    $mockUser = createMockSocialiteUser(
        id: '12345',
        name: 'Test User',
        nickname: 'testuser',
        email: 'test@example.com',
        avatar: 'https://example.com/avatar.jpg',
        token: 'mock-token'
    );

    $mockDriver = createMockGitHubDriver($mockUser);

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn($mockDriver);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirectToRoute('dashboard');

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'github_id' => '12345',
    ]);

    $user = User::query()->whereEmail('test@example.com')->first();
    expect($user->github_avatar)->toBe('https://example.com/avatar.jpg');
    expect(Auth::check())->toBeTrue();
});

it('updates existing user with github info', function (): void {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    Http::preventStrayRequests();

    $mockUser = createMockSocialiteUser(
        id: '67890',
        name: 'Existing User',
        nickname: 'existinguser',
        email: 'existing@example.com',
        avatar: 'https://example.com/new-avatar.jpg',
        token: 'mock-token'
    );

    $mockDriver = createMockGitHubDriver($mockUser);

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn($mockDriver);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirectToRoute('dashboard');

    $user->refresh();

    expect($user->github_id)->toBe('67890');
    expect($user->github_avatar)->toBe('https://example.com/new-avatar.jpg');
    expect(Auth::check())->toBeTrue();
});

it('logs in existing user without github info', function (): void {
    $user = User::factory()->create([
        'email' => 'no-github@example.com',
        'github_id' => null,
    ]);

    Http::preventStrayRequests();

    $mockUser = createMockSocialiteUser(
        id: '11111',
        name: 'No GitHub User',
        nickname: 'nogithub',
        email: 'no-github@example.com',
        avatar: 'https://example.com/avatar.jpg',
        token: 'mock-token'
    );

    $mockDriver = createMockGitHubDriver($mockUser);

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn($mockDriver);

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirectToRoute('dashboard');

    $user->refresh();

    expect($user->github_id)->toBe('11111');
    expect(Auth::check())->toBeTrue();
});
