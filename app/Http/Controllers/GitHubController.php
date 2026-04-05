<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HandleGitHubCallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

final readonly class GitHubController
{
    public function redirect(): Response
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(HandleGitHubCallback $action): RedirectResponse
    {
        $user = $action->handle();

        Auth::login($user);

        return to_route('dashboard');
    }
}
