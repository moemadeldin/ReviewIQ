<?php

declare(strict_types=1);

namespace App\Contracts;

interface GitHubAppAuth
{
    public function getInstallationToken(): string;

    public function refreshToken(): string;
}
