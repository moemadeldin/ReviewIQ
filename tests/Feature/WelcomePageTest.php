<?php

declare(strict_types=1);

use App\Models\User;

it('renders the welcome page for guests', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome'));
});

it('renders the welcome page for authenticated users with their session', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('auth.user')
            ->where('auth.user.id', $user->id));
});

it('ships the og image metadata needed for meta tags', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('og.url', config('app.og_image'))
            ->where('og.width', config('app.og_image_width'))
            ->where('og.height', config('app.og_image_height')));
});

it('exposes the built-in legal and contact pages', function (): void {
    foreach (['privacy', 'terms', 'contact'] as $route) {
        $this->get(route($route))->assertOk();
    }
});
