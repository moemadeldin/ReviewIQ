<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class OpenRouterReviewService implements AIReviewer
{
    private const int MAX_FALLBACK_RETRIES = 2;

    /**
     * @param  array<int, string>  $fallbackModels
     */
    public function __construct(
        private Client $client,
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private int $timeout = 60,
        private int $connectTimeout = 10,
        private array $fallbackModels = [],
        private bool $jsonObjectFormat = true,
    ) {
        throw_if($this->baseUrl === '' || $this->baseUrl === '0', InvalidArgumentException::class, 'Base URL cannot be empty.');
        throw_if($this->apiKey === '' || $this->apiKey === '0', InvalidArgumentException::class, 'API key cannot be empty.');
    }

    public function review(string $systemPrompt, string $userPrompt): array
    {
        $lastError = null;

        foreach ($this->models() as $model) {
            for ($attempt = 0; $attempt <= self::MAX_FALLBACK_RETRIES; $attempt++) {
                try {
                    $result = $this->attemptReview($model, $systemPrompt, $userPrompt);

                    return array_merge($result, ['meta' => [
                        'model' => $model,
                        'usage' => $result['usage'] ?? null,
                    ]]);
                } catch (ReviewParseException|RuntimeException|JsonException $error) {
                    $lastError = $error;

                    $status = $this->extractStatus($error);
                    $isRetryable = $this->isRetryableStatus($status);

                    if ($attempt < self::MAX_FALLBACK_RETRIES && $isRetryable) {
                        $delay = (int) (1000 * pow(2, $attempt)); // 1s, 2s
                        Log::warning('OpenRouter request failed, retrying', [
                            'model' => $model,
                            'attempt' => $attempt + 1,
                            'status' => $status,
                            'error' => $error->getMessage(),
                            'delay_ms' => $delay,
                        ]);
                        Sleep::msleep($delay);
                        continue;
                    }

                    Log::warning('OpenRouter model attempt failed, trying fallback model', [
                        'model' => $model,
                        'error' => $error->getMessage(),
                        'status' => $status,
                        'attempts' => $attempt + 1,
                    ]);

                    break;
                }
            }
        }

        throw $lastError ?? new ReviewParseException('No OpenRouter model configured');
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';
        $firstChunkEmitted = false;

        foreach ($this->models() as $model) {
            try {
                $response = $this->client->post($this->baseUrl.'chat/completions', [
                    'json' => $this->buildRequestBody($model, $systemPrompt, $userPrompt, stream: true),
                    'headers' => $this->buildHeaders(),
                    'stream' => true,
                    'read_timeout' => $this->timeout,
                    'connect_timeout' => $this->connectTimeout,
                ]);

                $body = $response->getBody();
                $reader = new SseStreamReader();

                $reader->read($body, function (string $line) use (&$fullContent, &$firstChunkEmitted, $onChunk, $model, &$response): void {
                    if (! str_starts_with($line, 'data: ')) {
                        return;
                    }

                    $data = trim(substr($line, 6));

                    if ($data === '[DONE]') {
                        return;
                    }

                    $json = json_decode($data, associative: true);
                    $chunk = $json['choices'][0]['delta']['content'] ?? '';

                    if ($chunk === '') {
                        return;
                    }

                    $fullContent .= $chunk;
                    $firstChunkEmitted = true;
                    $onChunk($chunk);
                });

                if ($fullContent === '') {
                    throw new ReviewParseException('OpenRouter returned empty streaming response');
                }

                $parsed = $this->parse($fullContent, $model);

                return array_merge($parsed, ['meta' => [
                    'model' => $model,
                    'usage' => $parsed['usage'] ?? null,
                ]]);

            } catch (GuzzleException|ReviewParseException|JsonException $guzzleException) {
                $status = $this->extractStatus($guzzleException);
                $isRetryable = $this->isRetryableStatus($status);

                // Only fallback if no chunks were emitted yet
                if (! $firstChunkEmitted && $isRetryable) {
                    Log::warning('OpenRouter streaming failed before first chunk, trying fallback', [
                        'model' => $model,
                        'status' => $status,
                        'error' => $guzzleException->getMessage(),
                    ]);
                    continue;
                }

                // If chunks were emitted, or not retryable, throw
                throw new RuntimeException(
                    sprintf('OpenRouter API error: %s', $guzzleException->getMessage()),
                    $guzzleException->getCode(),
                    $guzzleException,
                );
            }
        }

        throw new ReviewParseException('All OpenRouter models failed');
    }

    private function attemptReview(string $model, string $systemPrompt, string $userPrompt): array
    {
        $response = $this->send($systemPrompt, $userPrompt, $model);
        $raw = $response['choices'][0]['message']['content'] ?? '';

        if ($raw === '') {
            $finishReason = $response['choices'][0]['finish_reason'] ?? null;
            $error = $response['error'] ?? null;

            Log::error('OpenRouter returned empty response', [
                'model' => $model,
                'finish_reason' => $finishReason,
                'error' => $error,
                'response_length' => strlen((string) json_encode($response)),
            ]);

            $hint = $finishReason === 'length'
                ? ' (finish_reason: length - increase OPENROUTER_MAX_TOKENS)'
                : '';

            throw new ReviewParseException('OpenRouter returned empty response'.$hint);
        }

        $parsed = $this->parse($raw, $model);

        // Capture usage from response
        if (isset($response['usage']) && is_array($response['usage'])) {
            $parsed['usage'] = $response['usage'];
        }

        return $parsed;
    }

    /**
     * @return array<int, string>
     */
    private function models(): array
    {
        return array_values(array_unique(array_filter([
            $this->model,
            ...$this->fallbackModels,
        ])));
    }

    private function send(string $systemPrompt, string $userPrompt, string $model): array
    {
        try {
            $response = $this->client->post($this->baseUrl.'chat/completions', [
                'json' => $this->buildRequestBody($model, $systemPrompt, $userPrompt, stream: false),
                'headers' => $this->buildHeaders(),
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                sprintf('OpenRouter API error: %s', $e->getMessage()),
                $e->getCode(),
                $e,
            );
        }

        return json_decode((string) $response->getBody(), associative: true);
    }

    private function buildRequestBody(string $model, string $systemPrompt, string $userPrompt, bool $stream): array
    {
        $body = [
            'model' => $model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ];

        if ($stream) {
            $body['stream'] = true;
        } elseif ($this->jsonObjectFormat) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        return $body;
    }

    private function buildHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->apiKey];
    }

    private function extractStatus(\Throwable $e): ?int
    {
        if ($e instanceof RequestException) {
            return $e->getResponse()?->getStatusCode();
        }
        return null;
    }

    private function isRetryableStatus(?int $status): bool
    {
        return $status === 429 || ($status !== null && $status >= 500);
    }

    private function parse(string $raw, string $model): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = mb_trim((string) preg_replace('/\s*```$/m', '', $clean));

        $firstBrace = mb_strpos($clean, '{');
        $lastBrace = mb_strrpos($clean, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            $clean = mb_substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($clean, associative: true);

        if (! is_array($parsed)) {
            $jsonError = json_last_error_msg();

            $parsed = json_decode($this->repairJson($clean), associative: true);

            if (! is_array($parsed)) {
                Log::error('Failed to parse OpenRouter response', [
                    'model' => $model,
                    'error' => $jsonError,
                    'response_length' => strlen($raw),
                ]);

                throw new ReviewParseException('Invalid JSON from OpenRouter: '.$jsonError);
            }
        }

        $missing = array_diff(['summary', 'score', 'issues'], array_keys($parsed));

        throw_unless(
            $missing === [],
            ReviewParseException::class,
            'OpenRouter response missing required fields (missing: '.implode(', ', $missing).')',
        );

        return $this->sanitize($parsed);
    }

    private function repairJson(string $json): string
    {
        [$masked, $bodies] = $this->maskStringBodies($json);

        $json = (string) preg_replace('/(?<=[\s,{])(\w+)"(?=\s*:)/', '"$1"', $masked);
        $json = (string) preg_replace('/"(\w+)\s+\[/', '"$1": [', $json);
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)\s+"([^"]+)"/', '"$1": "$2"', $json);
        $json = (string) preg_replace('/:\s*([a-zA-Z_]\w*)"?([,}\]]|$)/', ': "$1"$2', $json);
        $json = (string) preg_replace('/,\s*"\s*([}\]])/', '$1', $json);
        $json = (string) preg_replace('/,\s*([}\]])/', '$1', $json);
        $json = (string) preg_replace('/:\s*,/', ': null,', $json);

        return (string) strtr($json, $bodies);
    }

    /**
     * Replace string literal bodies with placeholder tokens so repair rules never
     * match inside string content (e.g. the value "coverage: filtering").
     *
     * A quote is treated as opening a string only when preceded by a structural
     * character, so malformed keys like `summary": "value"` are left untouched.
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

    private function sanitize(array $parsed): array
    {
        $parsed['score'] = is_numeric($parsed['score'] ?? null) ? max(0, min(100, (int) $parsed['score'])) : 0;

        $allowedSeverities = ['critical', 'high', 'medium', 'low', 'praise'];
        $allowedRecommendations = ['approve', 'request_changes', 'comment'];
        $allowedCategories = ['security', 'performance', 'error_handling', 'correctness', 'maintainability', 'style', 'testing'];

        $parsed['issues'] = array_values(array_map(
            function (array $issue) use ($allowedSeverities, $allowedCategories): array {
                $issue['line'] = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
                $issue['severity'] = in_array($issue['severity'] ?? null, $allowedSeverities, true)
                    ? $issue['severity']
                    : 'medium';

                // Move praise to highlights
                if ($issue['severity'] === 'praise') {
                    return ['_move_to_highlights' => true, 'content' => $issue['description'] ?? $issue['title'] ?? ''];
                }

                $issue['category'] = in_array($issue['category'] ?? null, $allowedCategories, true)
                    ? $issue['category']
                    : 'maintainability';

                $issue['description'] ??= $issue['title'] ?? $issue['message'] ?? '';
                unset($issue['message']); // Normalize to description only

                return $issue;
            },
            $parsed['issues'] ?? []
        ));

        // Extract praise items to highlights
        $praiseItems = array_filter($parsed['issues'], fn ($i) => isset($i['_move_to_highlights']));
        $parsed['issues'] = array_values(array_filter($parsed['issues'], fn ($i) => ! isset($i['_move_to_highlights'])));

        foreach ($praiseItems as $praise) {
            $parsed['highlights'][] = [
                'file' => '',
                'line' => null,
                'content' => $praise['content'],
            ];
        }

        $parsed['highlights'] = array_values(array_filter(
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

        $parsed['recommendation'] = in_array($parsed['recommendation'] ?? null, $allowedRecommendations, true)
            ? $parsed['recommendation']
            : 'comment';

        $parsed['score_rationale'] ??= '';

        return $parsed;
    }
}