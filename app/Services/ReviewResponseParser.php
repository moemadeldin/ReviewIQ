<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewParseException;
use App\Utilities\Constants;

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
     *     meta: array{model: string, usage?: array<mixed>|null},
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
            throw_unless(is_array($parsed), ReviewParseException::class, 'Invalid JSON from OpenRouter: '.$jsonError);
        }

        $this->validateRequiredFields($parsed);

        return $this->sanitize($parsed, $model);
    }

    private function stripFences(string $raw): string
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*/m', '', $raw);

        return mb_trim((string) preg_replace('/\s*```$/m', '', $clean));
    }

    private function extractJsonObject(string $clean): string
    {
        $firstBrace = mb_strpos($clean, '{');
        $lastBrace = mb_strrpos($clean, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            return mb_substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $clean;
    }

    /**
     * @param  array<mixed>  $parsed
     */
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
     * @param  array<mixed>  $parsed
     * @return array{
     *     summary: string,
     *     score: int,
     *     score_rationale: string,
     *     issues: array<int, array{file: string, line: int|null, severity: string, description: string, category: string}>,
     *     highlights: array<int, array{file: string, line: int|null, content: string}>,
     *     recommendation: string,
     *     meta: array{model: string, usage?: array<mixed>|null},
     * }
     */
    private function sanitize(array $parsed, string $model): array
    {
        $allowedSeverities = ['critical', 'high', 'medium', 'low', 'praise'];
        $allowedRecommendations = ['approve', 'request_changes', 'comment'];
        $allowedCategories = ['security', 'performance', 'error_handling', 'correctness', 'maintainability', 'style', 'testing'];

        $score = is_numeric($parsed['score'] ?? null) ? max(Constants::AI_SCORE_MIN, min(Constants::AI_SCORE_MAX, (int) $parsed['score'])) : Constants::AI_SCORE_MIN;

        $rawIssues = is_array($parsed['issues'] ?? null) ? $parsed['issues'] : [];

        $issues = array_values(array_map(
            function (mixed $issue) use ($allowedSeverities, $allowedCategories): array {
                if (! is_array($issue)) {
                    return ['file' => '', 'line' => null, 'severity' => 'medium', 'description' => '', 'category' => 'maintainability'];
                }

                $line = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
                $severity = in_array($issue['severity'] ?? null, $allowedSeverities, true)
                    ? $issue['severity']
                    : 'medium';

                // Move praise to highlights
                if ($severity === 'praise') {
                    return ['_move_to_highlights' => true, 'content' => $this->toString($issue['description'] ?? $issue['title'] ?? null)];
                }

                $category = in_array($issue['category'] ?? null, $allowedCategories, true)
                    ? $issue['category']
                    : 'maintainability';

                $description = $this->toString($issue['description'] ?? $issue['title'] ?? $issue['message'] ?? null);
                // Normalize: only 'description' field, no 'message' or 'title'
                unset($issue['message'], $issue['title']);

                return [
                    'file' => $this->toString($issue['file'] ?? null),
                    'line' => $line,
                    'severity' => $severity,
                    'description' => $description,
                    'category' => $category,
                ];
            },
            $rawIssues,
        ));

        // Extract praise items to highlights
        $praiseItems = array_filter($issues, fn (array $i): bool => isset($i['_move_to_highlights']));
        $issues = array_values(array_filter($issues, fn (array $i): bool => ! isset($i['_move_to_highlights'])));

        $rawHighlights = is_array($parsed['highlights'] ?? null) ? $parsed['highlights'] : [];

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
            }, $rawHighlights),
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
            'summary' => $this->toString($parsed['summary'] ?? null),
            'score' => $score,
            'score_rationale' => $this->toString($parsed['score_rationale'] ?? null),
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

    private function toString(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function repairJson(string $json): string
    {
        // Fix unclosed quote on key before bracket: "key [ -> "key": [
        $json = (string) preg_replace('/"(\w+)\s*\[/', '"$1": [', $json);
        // Fix unquoted key before bracket: key [ -> "key": [
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)\s*\[/', '"$1": [', $json);
        // Fix key missing opening quote before colon: key": -> "key":
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)":/', '"$1":', $json);
        // Fix unquoted key before colon: key" -> "key" (when followed by colon)
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)"(?=\s*:)/', '"$1', $json);
        // Fix unquoted key directly before colon: key: -> "key":
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)(?=\s*:)/', '"$1"', $json);
        // Fix missing colon before bracket (quoted key): "issues"[ -> "issues": [
        $json = (string) preg_replace('/"(\w+)"\s*\[/', '"$1": [', $json);
        // Fix unquoted key + quoted value: key "value" -> "key": "value"
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)\s+"([^"]+)"/', '"$1": "$2"', $json);
        // Fix missing colon between key and value: key"value" -> "key": "value" (single line only)
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)"([^"\n]+)"/', '"$1": "$2"', $json);
        // Fix unquoted value with trailing quote: :value" -> :"value"
        $json = (string) preg_replace('/:\s*([a-zA-Z_]\w*)"(?=\s*[,\}])/', ': "$1"', $json);
        // Fix unquoted string values: :value, -> :"value",
        $json = (string) preg_replace('/:\s*([a-zA-Z_]\w*)(?=\s*[,\}])/', ': "$1"', $json);
        // Fix unquoted values in arrays: [value,] -> ["value",
        $json = (string) preg_replace('/\[\s*([a-zA-Z_]\w*)(?=\s*[,\}])/', '["$1"', $json);
        // Fix trailing commas
        $json = (string) preg_replace('/,\s*"\s*([}\]])/', '$1', $json);
        $json = (string) preg_replace('/,\s*([}\]])/', '$1', $json);

        return (string) preg_replace('/:\s*,/', ': null,', $json);
    }
}
