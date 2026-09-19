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
use RuntimeException;

final readonly class OpenRouterReviewService implements AIReviewer
{
    private const int STREAM_READ_CHUNK = 1024;

    private const int STREAM_SLEEP_US = 10_000;

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
        private array $fallbackModels = [],
    ) {
        throw_if($this->baseUrl === '' || $this->baseUrl === '0', InvalidArgumentException::class, 'Base URL cannot be empty.');
        throw_if($this->apiKey === '' || $this->apiKey === '0', InvalidArgumentException::class, 'API key cannot be empty.');
    }

    public function review(string $systemPrompt, string $userPrompt): array
    {
        $lastError = null;

        foreach ($this->models() as $model) {
            try {
                return $this->attemptReview($model, $systemPrompt, $userPrompt);
            } catch (ReviewParseException|RuntimeException $error) {
                $lastError = $error;

                Log::warning('OpenRouter model attempt failed, trying fallback model', [
                    'model' => $model,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        throw $lastError ?? new ReviewParseException('No OpenRouter model configured');
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';

        try {
            $response = $this->client->post($this->baseUrl.'chat/completions', [
                'json' => $this->buildRequestBody($this->model, $systemPrompt, $userPrompt, stream: true),
                'headers' => $this->buildHeaders(),
                'stream' => true,
                'read_timeout' => $this->timeout,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $line = $body->read(self::STREAM_READ_CHUNK);

                if ($line === '' || $line === '0') {
                    Sleep::usleep(self::STREAM_SLEEP_US);

                    continue;
                }

                foreach (explode("\n", $line) as $rawLine) {
                    if (! str_starts_with($rawLine, 'data: ')) {
                        continue;
                    }

                    $data = mb_trim(mb_substr($rawLine, 6));

                    if ($data === '[DONE]') {
                        break 2;
                    }

                    /** @var array{choices: array<int, array{delta: array{content: string}}>}|null $json */
                    $json = json_decode($data, associative: true);
                    $chunk = $json['choices'][0]['delta']['content'] ?? '';

                    if ($chunk === '') {
                        continue;
                    }

                    $fullContent .= $chunk;
                    $onChunk($chunk);
                }
            }
        } catch (GuzzleException $guzzleException) {
            $this->handleGuzzleException($guzzleException, 'streaming', $this->model);
        }

        throw_if($fullContent === '', ReviewParseException::class, 'OpenRouter returned empty streaming response');

        return $this->parse($fullContent, $this->model);
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
                'response' => mb_substr((string) json_encode($response), 0, 1000),
            ]);

            $hint = $finishReason === 'length'
                ? ' (finish_reason: length - increase OPENROUTER_MAX_TOKENS)'
                : '';

            throw new ReviewParseException('OpenRouter returned empty response'.$hint);
        }

        return $this->parse($raw, $model);
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
                'read_timeout' => $this->timeout,
            ]);
        } catch (GuzzleException $guzzleException) {
            $this->handleGuzzleException($guzzleException, 'review', $model);
        }

        return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
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
        }

        return $body;
    }

    private function buildHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->apiKey];
    }

    private function handleGuzzleException(GuzzleException $e, string $context, string $model): never
    {

        $status = $e instanceof RequestException
            ? $e->getResponse()?->getStatusCode()
            : null;

        Log::error(sprintf('OpenRouter API %s request failed', $context), [
            'model' => $model,
            'status' => $status,
        ]);

        throw new RuntimeException(
            sprintf('OpenRouter API error: %s', $e->getMessage()),
            $e->getCode(),
            $e,
        );
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
                    'raw' => mb_substr($raw, 0, 1000),
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
        $length = mb_strlen($json);
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

            if ($close >= $length || $json[$close - 1] !== '"') {
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

            $full = mb_substr($json, $i, $close - $i);
            $token = "\x1ASTR{$index}\x1A";
            $bodies[$token] = mb_substr($full, 1, -1);
            $masked .= '"'.$token.'"';
            $index++;
            $i = $close - 1;
        }

        return [$masked, $bodies];
    }

    private function sanitize(array $parsed): array
    {
        $parsed['score'] = is_numeric($parsed['score'] ?? null) ? (int) $parsed['score'] : 0;

        $parsed['issues'] = array_values(array_map(function (array $issue): array {
            $issue['line'] = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
            $issue['severity'] = in_array($issue['severity'] ?? null, ['critical', 'high', 'medium', 'low', 'praise'], strict: true)
                ? $issue['severity']
                : 'medium';
            $issue['message'] ??= $issue['description'] ?? $issue['title'] ?? '';

            return $issue;
        }, $parsed['issues'] ?? []));

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

        $parsed['recommendation'] ??= 'comment';
        $parsed['score_rationale'] ??= '';

        return $parsed;
    }
}
