<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HandleGitHubCallback;
use App\Exceptions\GitHubAccountAlreadyLinkedException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

final readonly class GitHubController
{
    public function redirect(): Response
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request, HandleGitHubCallback $action): RedirectResponse
    {
        $currentUser = $request->user();

        try {
            $user = $action->handle($currentUser instanceof User ? $currentUser : null);
        } catch (GitHubAccountAlreadyLinkedException) {
            return redirect()
                ->route('repos.index')
                ->withErrors(['github' => 'A user has already authenticated with this GitHub account.']);
        }

        if ($currentUser === null) {
            Auth::login($user);

            return to_route('dashboard');
        }

        return to_route('repos.index');
    }
}
