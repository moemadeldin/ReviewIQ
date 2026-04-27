<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIReviewer
{
    /**
     * @return array{content: string}
     */
    public function review(string $systemPrompt, string $userPrompt): array;

    /**
     * @return array{content: string}
     */
    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array;
}
