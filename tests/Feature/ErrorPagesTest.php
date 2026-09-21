<?php

declare(strict_types=1);

use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\Response;

it('exists for every supported error status view', function (): void {
    foreach ([
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED,
        Response::HTTP_FORBIDDEN,
        Response::HTTP_NOT_FOUND,
        Response::HTTP_METHOD_NOT_ALLOWED,
        Response::HTTP_CONFLICT,
        Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
        Constants::HTTP_PAGE_EXPIRED,
        Response::HTTP_UNPROCESSABLE_ENTITY,
        Response::HTTP_TOO_MANY_REQUESTS,
        Response::HTTP_INTERNAL_SERVER_ERROR,
        Response::HTTP_BAD_GATEWAY,
        Response::HTTP_SERVICE_UNAVAILABLE,
        Response::HTTP_GATEWAY_TIMEOUT,
    ] as $code) {
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

    $response->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED)
        ->assertSee('405')
        ->assertSee('does not support');
});
