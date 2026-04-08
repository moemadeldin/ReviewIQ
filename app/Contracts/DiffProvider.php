<?php

declare(strict_types=1);

namespace App\Contracts;

interface DiffProvider
{
    public function getDiff(string $token, string $repoFullName, int $prNumber): string;
}
