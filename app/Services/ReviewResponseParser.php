<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewParseException;
use JsonException;
use RuntimeException;

final readonly class ReviewResponseParser
{
    /**
     * @return array{
     *     summary: string,
     *     score: int,
     *     score_rationale: string,
     *     issues: array<int, array{file: string, line: int|null, severity: string, description: string, category: string}>,
     *     highlights: array<int, array{file: string, line: int|null, content: string}>,
     *     recommendation: string,
     *     meta?: array{model: string, usage?: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>
     * }
     */
    public function parse(string $raw, string $model): array
    {
        $clean = $this->stripFences($raw);
        $clean = $this->extractJsonObject($clean);

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($clean, associative: true);

        if (! is_array($parsed)) {
            $jsonError = json_last_error_msg();

            $parsed = json_decode($this->repairJson($clean), associative: true);

            if (! is_array($parsed)) {
                throw new ReviewParseException('Invalid JSON from OpenRouter: '.$jsonError);
            }
        }

        $this->validateRequiredFields($parsed);

        return $this->sanitize($parsed, $model);
    }

    private function stripFences(string $raw): string
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = mb_trim((string) preg_replace('/\s*```$/m', '', $clean));

        return $clean;
    }

    private function extractJsonObject(string $clean): string
    {
        $firstBrace = mb_strpos($clean, '{');
        $lastBrace = mb_strrpos($clean, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            $clean = mb_substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $clean;
    }

    private function validateRequiredFields(array $parsed): void
    {
        $missing = array_diff(['summary', 'score', 'issues'], array_keys($parsed));

        throw_unless(
            $missing === [],
            ReviewParseException::class,
            'OpenRouter response missing required fields (missing: '.implode(', ', $missing).')',
        );
    }

    /**
     * @return array{
     *     summary: string,
     *     score: int,
     *     score_rationale: string,
     *     issues: array<int, array{file: string, line: int|null, severity: string, description: string, category: string}>,
     *     highlights: array<int, array{file: string, line: int|null, content: string}>,
     *     recommendation: string,
     *     meta?: array{model: string, usage?: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>
     * }
     */
    private function sanitize(array $parsed, string $model): array
    {
        $allowedSeverities = ['critical', 'high', 'medium', 'low', 'praise'];
        $allowedRecommendations = ['approve', 'request_changes', 'comment'];
        $allowedCategories = ['security', 'performance', 'error_handling', 'correctness', 'maintainability', 'style', 'testing'];

        $score = is_numeric($parsed['score'] ?? null) ? max(0, min(100, (int) $parsed['score'])) : 0;

        $issues = array_values(array_map(
            function (array $issue) use ($allowedSeverities, $allowedCategories): array {
                $line = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
                $severity = in_array($issue['severity'] ?? null, $allowedSeverities, true)
                    ? $issue['severity']
                    : 'medium';

                // Move praise to highlights
                if ($severity === 'praise') {
                    return ['_move_to_highlights' => true, 'content' => $issue['description'] ?? $issue['title'] ?? ''];
                }

                $category = in_array($issue['category'] ?? null, $allowedCategories, true)
                    ? $issue['category']
                    : 'maintainability';

                $description = $issue['description'] ?? $issue['title'] ?? $issue['message'] ?? '';
                // Normalize: only 'description' field, no 'message' or 'title'
                unset($issue['message'], $issue['title']);

                return [
                    'file' => $issue['file'] ?? '',
                    'line' => $line,
                    'severity' => $severity,
                    'description' => $description,
                    'category' => $category,
                ];
            },
            $parsed['issues'] ?? []
        ));

        // Extract praise items to highlights
        $praiseItems = array_filter($issues, fn ($i) => isset($i['_move_to_highlights']));
        $issues = array_values(array_filter($issues, fn ($i) => ! isset($i['_move_to_highlights'])));

        $highlights = array_values(array_filter(
            array_map(function (mixed $highlight): ?array {
                if (is_string($highlight)) {
                    return ['file' => '', 'line' => null, 'content' => $highlight];
                }

                if (! is_array($highlight)) {
                    return null;
                }

                return [
                    'file' => isset($highlight['file']) && is_string($highlight['file']) ? $highlight['file'] : '',
                    'line' => isset($highlight['line']) && is_numeric($highlight['line']) ? (int) $highlight['line'] : null,
                    'content' => isset($highlight['content']) && is_string($highlight['content']) ? $highlight['content'] : '',
                ];
            }, $parsed['highlights'] ?? []),
            fn (?array $h): bool => $h !== null && $h['content'] !== '',
        ));

        foreach ($praiseItems as $praise) {
            $highlights[] = [
                'file' => '',
                'line' => null,
                'content' => $praise['content'],
            ];
        }

        $recommendation = in_array($parsed['recommendation'] ?? null, $allowedRecommendations, true)
            ? $parsed['recommendation']
            : 'comment';

        $result = [
            'summary' => (string) ($parsed['summary'] ?? ''),
            'score' => $score,
            'score_rationale' => (string) ($parsed['score_rationale'] ?? ''),
            'issues' => $issues,
            'highlights' => $highlights,
            'recommendation' => $recommendation,
        ];

        if (isset($parsed['usage']) && is_array($parsed['usage'])) {
            $result['meta'] = [
                'model' => $model,
                'usage' => $parsed['usage'],
            ];
        } else {
            $result['meta'] = ['model' => $model];
        }

        return $result;
    }

    private function repairJson(string $json): string
    {
        [$masked, $bodies] = $this->maskStringBodies($json);

        // Fix unquoted keys: {summary:"value"} -> {"summary":"value"}
        $json = (string) preg_replace('/(?<=[\{,])\s*([a-zA-Z_]\w*)\s*:/', '"$1":', $masked);
        // Fix missing colon before bracket: "issues"[...] -> "issues": [...]
        $json = (string) preg_replace('/"(\w+)"\s*(\[)/', '"$1": $2', $json);
        // Fix unquoted string values: :value, -> :"value",
        $json = (string) preg_replace('/:\s*([a-zA-Z_]\w*)(?=\s*[,\}])/', ': "$1"', $json);
        // Fix trailing commas
        $json = (string) preg_replace('/,\s*"\s*([}\]])/', '$1', $json);
        $json = (string) preg_replace('/,\s*([}\]])/', '$1', $json);
        $json = (string) preg_replace('/:\s*,/', ': null,', $json);
        // Fix unquoted keys followed by quote: key"value" -> "key":"value"
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)\s+"([^"]+)"/', '"$1": "$2"', $json);

        return (string) strtr($json, $bodies);
    }

    /**
     * Replace string literal bodies with placeholder tokens so repair rules never
     * match inside string content (e.g. the value "coverage: filtering").
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function maskStringBodies(string $json): array
    {
        $length = strlen($json);
        $masked = '';
        $bodies = [];
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($char !== '"') {
                $masked .= $char;
                continue;
            }

            $previous = $i > 0 ? $json[$i - 1] : '{';
            $startsString = str_contains('{[, :', $previous) || ctype_space($previous);

            if (! $startsString) {
                $masked .= $char;
                continue;
            }

            $close = $i + 1;

            while ($close < $length) {
                $inner = $json[$close];

                if ($inner === '\\') {
                    $close += 2;
                    continue;
                }

                if ($inner === '"') {
                    $close++;
                    break;
                }

                $close++;
            }

            if ($close > $length || $json[$close - 1] !== '"') {
                $masked .= $char;
                continue;
            }

            $followsString = $close >= $length
                || str_contains(':,}] ', $json[$close])
                || ctype_space($json[$close]);

            if (! $followsString) {
                $masked .= $char;
                continue;
            }

            $full = substr($json, $i, $close - $i);
            $token = "\x1ASTR{$index}\x1A";
            $bodies[$token] = substr($full, 1, -1);
            $masked .= '"'.$token.'"';
            $index++;
            $i = $close - 1;
        }

        return [$masked, $bodies];
    }
}