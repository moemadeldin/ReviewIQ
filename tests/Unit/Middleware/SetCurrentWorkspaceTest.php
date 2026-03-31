<?php

declare(strict_types=1);

use App\Http\Middleware\RequireWorkspace;
use App\Http\Middleware\SetCurrentWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

it('sets null workspace when user is guest', function (): void {
    $middleware = new SetCurrentWorkspace();

    $request = Request::create('/', 'GET');
    $response = $middleware->handle($request, fn ($req): Response => new Response());

    expect($request->attributes->get('current_workspace'))->toBeNull();
});

it('passes through when user has no workspaces', function (): void {
    $user = User::factory()->create();

    $middleware = new SetCurrentWorkspace();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle($request, fn ($req): Response => new Response());

    expect($request->attributes->get('current_workspace'))->toBeNull();
    expect($response->getStatusCode())->toBe(200);
});

it('require workspace passes through when user is guest', function (): void {
    $middleware = new RequireWorkspace();

    $request = Request::create('/', 'GET');
    $response = $middleware->handle($request, fn ($req): Response => new Response());

    expect($response->getStatusCode())->toBe(200);
});

it('require workspace passes through for excepted routes', function (): void {
    $user = User::factory()->create();

    $middleware = new RequireWorkspace();

    $request = Request::create('/workspaces/create', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/workspaces/create', fn (): string => 'ok');
    $route->name('workspaces.create');

    $request->setRouteResolver(fn (): Route => $route);

    $response = $middleware->handle($request, fn ($req): Response => new Response());

    expect($response->getStatusCode())->toBe(200);
});

it('require workspace redirects to create when user has no workspaces', function (): void {
    $user = User::factory()->create();

    $middleware = new RequireWorkspace();

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route('GET', '/dashboard', fn (): string => 'ok');
    $route->name('dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $response = $middleware->handle($request, fn ($req): Response => new Response());

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toContain('workspaces/create');
});
