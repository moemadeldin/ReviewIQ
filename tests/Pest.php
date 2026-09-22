<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();
        Turnstile::fake();

        $this->freezeTime();
    })
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    // ..
}

function turnstileToken(): string
{
    return 'dummy-turnstile-token';
}

function createWorkspaceForUser(User $user): Workspace
{
    return Workspace::factory()->withOwner($user)->create();
}
