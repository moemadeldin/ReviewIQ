<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

final readonly class GitHubRedirectController
{
    public function __invoke(): Response
    {
        return Socialite::driver('github')->redirect();
    }
}
