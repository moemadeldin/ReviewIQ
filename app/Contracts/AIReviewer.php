<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIReviewer
{
    /**
     * @return array{
     *     summary: string,
     *     score: int,
     *     score_rationale: string,
     *     issues: array<int, array{file: string, line: int|null, severity: string, description: string, category: string}>,
     *     highlights: array<int, array{file: string, line: int|null, content: string}>,
     *     recommendation: string,
     *     meta: array{model: string, usage: array<mixed>|null}
     * }
     */
    public function review(string $systemPrompt, string $userPrompt): array;

    /**
     * @return array{
     *     summary: string,
     *     score: int,
     *     score_rationale: string,
     *     issues: array<int, array{file: string, line: int|null, severity: string, description: string, category: string}>,
     *     highlights: array<int, array{file: string, line: int|null, content: string}>,
     *     recommendation: string,
     *     meta: array{model: string, usage: array<mixed>|null}
     * }
     */
    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array;
}
