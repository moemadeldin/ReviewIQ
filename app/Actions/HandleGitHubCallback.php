<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

final readonly class HandleGitHubCallback
{
    public function handle(): User
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

        return $user;
    }
}
