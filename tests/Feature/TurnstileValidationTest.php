<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

it('requires a turnstile token on login', function (): void {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('turnstile_token');

    $this->assertGuest();
});

it('rejects login with invalid turnstile token', function (): void {
    Turnstile::fake()->fail();

    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'turnstile_token' => 'invalid-token',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('turnstile_token');

    $this->assertGuest();
});

it('requires a turnstile token on registration', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('turnstile_token');

    expect(User::query()->whereEmail('test@example.com')->exists())->toBeFalse();
});

it('requires a turnstile token on forgot password', function (): void {
    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHasErrors('turnstile_token');
});

it('requires a turnstile token on reset password', function (): void {
    $response = $this->fromRoute('password.reset', ['token' => 'fake-token'])
        ->post(route('password.store'), [
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'token' => 'fake-token',
        ]);

    $response->assertRedirect(route('password.reset', ['token' => 'fake-token']))
        ->assertSessionHasErrors('turnstile_token');
});
