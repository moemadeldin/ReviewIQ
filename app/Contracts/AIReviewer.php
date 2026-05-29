<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIReviewer
{
    /**
     * @return array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, array{}>, highlights?: array<int, string>, recommendation?: string}
     */
    public function review(string $systemPrompt, string $userPrompt): array;

    /**
     * @return array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, array{}>, highlights?: array<int, string>, recommendation?: string}
     */
    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array;
}
