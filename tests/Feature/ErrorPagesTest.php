<?php

declare(strict_types=1);

use App\Models\User;

it('exists for every supported error status view', function (): void {
    foreach ([400, 401, 403, 404, 405, 409, 413, 419, 422, 429, 500, 502, 503, 504] as $code) {
        expect(view()->exists('errors.'.$code))->toBeTrue();
    }
});

it('renders the styled 404 page for unknown routes', function (): void {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound()
        ->assertSee('404')
        ->assertSee('could not be found')
        ->assertSee('Back to Home');
});

it('renders the styled 404 page pointing to the dashboard for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('Back to Dashboard');
});

it('renders the styled 405 page for a method not allowed on an existing route', function (): void {
    $response = $this->patch('/dashboard');

    $response->assertStatus(405)
        ->assertSee('405')
        ->assertSee('does not support');
});
