<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
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

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();
        $user = User::query()
            ->where('email', $githubUser->email)
            ->first();
        if ($user === null) {
            $user = User::query()->create([
                'name' => $githubUser->name ?? $githubUser->nickname,
                'email' => $githubUser->email,
                'github_id' => $githubUser->id,
                'github_avatar' => $githubUser->avatar,
                'github_token' => $githubUser->token,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'github_id' => $githubUser->id,
                'github_avatar' => $githubUser->avatar,
                'github_token' => $githubUser->token,
            ]);
        }

        Auth::login($user);

        return to_route('dashboard');
    }
}
