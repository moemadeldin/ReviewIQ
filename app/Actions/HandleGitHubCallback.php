<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\GitHubAccountAlreadyLinkedException;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

final readonly class HandleGitHubCallback
{
    /**
     * @throws GitHubAccountAlreadyLinkedException
     */
    public function handle(?User $currentUser = null): User
    {
        $githubUser = Socialite::driver('github')->user();

        assert($githubUser instanceof SocialiteUser);

        $githubId = (string) $githubUser->getId();

        $linkedUser = User::query()
            ->where('github_id', $githubId)
            ->first();

        if ($linkedUser !== null) {
            if ($currentUser !== null && $currentUser->isNot($linkedUser)) {
                throw new GitHubAccountAlreadyLinkedException(
                    'A user has already authenticated with this GitHub account.'
                );
            }

            $linkedUser->update([
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]);

            return $linkedUser;
        }

        if ($currentUser !== null) {
            $currentUser->update([
                'github_id' => $githubId,
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]);

            return $currentUser;
        }

        $user = User::query()
            ->where('email', $githubUser->email)
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'github_id' => $githubId,
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'github_id' => $githubId,
                'github_avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]);
        }

        return $user;
    }
}
