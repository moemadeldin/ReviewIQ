<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\GitHubAccountAlreadyLinkedException;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

final readonly class HandleGitHubCallback
{
    /**
     * @throws GitHubAccountAlreadyLinkedException
     * @throws InvalidStateException
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
            throw_if($currentUser instanceof User && $currentUser->isNot($linkedUser), GitHubAccountAlreadyLinkedException::class, 'A user has already authenticated with this GitHub account.');

            return $this->refreshGithubCredentials($linkedUser, $githubUser);
        }

        if ($currentUser instanceof User) {
            $this->refreshGithubCredentials($currentUser, $githubUser, $githubId);

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
            $this->refreshGithubCredentials($user, $githubUser, $githubId);
        }

        return $user;
    }

    /**
     * Persist the freshly issued GitHub credentials without decrypting the
     * previously stored token, which may be unreadable after an APP_KEY change.
     */
    private function refreshGithubCredentials(User $user, SocialiteUser $githubUser, ?string $githubId = null): User
    {
        User::query()->whereKey($user->id)->update([
            'github_id' => $githubId ?? $user->github_id,
            'github_avatar' => $githubUser->getAvatar(),
            'github_token' => Crypt::encryptString($githubUser->token),
        ]);

        return $user->refresh();
    }
}
