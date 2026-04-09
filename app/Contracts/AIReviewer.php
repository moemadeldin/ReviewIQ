<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIReviewer
{
    public function review(string $systemPrompt, string $userPrompt): array;

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array;
}
