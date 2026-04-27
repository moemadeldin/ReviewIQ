<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

final readonly class HandleGitHubCallback
{
    public function handle(): User
    {
        $githubUser = Socialite::driver('github')->user();

        assert($githubUser instanceof SocialiteUser);

        $user = User::query()
            ->where('email', $githubUser->email)
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'github_id' => (string) $githubUser->getId(),
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'github_id' => (string) $githubUser->getId(),
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]);
        }

        return $user;
    }
}
