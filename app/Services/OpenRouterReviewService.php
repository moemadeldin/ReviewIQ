<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use RuntimeException;

final readonly class OpenRouterReviewService implements AIReviewer
{
    public function __construct(
        private Client $client,
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private int $timeout = 60,
    ) {
        throw_if($this->baseUrl === '' || $this->baseUrl === '0', InvalidArgumentException::class, 'Base URL cannot be empty.');
        if (empty($this->baseUrl)) {
            throw new InvalidArgumentException('Base URL cannot be empty.');
        }
    }

    public function review(string $systemPrompt, string $userPrompt): array
    {
        $response = $this->send($systemPrompt, $userPrompt, stream: false);
        $raw = $response['choices'][0]['message']['content'] ?? '';

        throw_if($raw === '', ReviewParseException::class, 'OpenRouter returned empty response');

        return $this->parse($raw);
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';

        try {
            $response = $this->client->post($this->baseUrl.'chat/completions', [
                'json' => $this->buildRequestBody($systemPrompt, $userPrompt, stream: true),
                'headers' => $this->buildHeaders(),
                'stream' => true,
                'read_timeout' => $this->timeout,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $line = $body->read(1024);

                if ($line === '' || $line === '0') {
                    Sleep::usleep(10000);

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
        } catch (RequestException $requestException) {
            $this->handleRequestException($requestException, 'streaming');
        }

        throw_if($fullContent === '', ReviewParseException::class, 'OpenRouter returned empty streaming response');

        return $this->parse($fullContent);
    }

    private function send(string $systemPrompt, string $userPrompt, bool $stream): array
    {
        try {
            $response = $this->client->post($this->baseUrl.'chat/completions', [
                'json' => $this->buildRequestBody($systemPrompt, $userPrompt, stream: $stream),
                'headers' => $this->buildHeaders(),
            ]);
        } catch (RequestException $requestException) {
            $this->handleRequestException($requestException, 'review');
        }

        return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function buildRequestBody(string $systemPrompt, string $userPrompt, bool $stream): array
    {
        $body = [
            'model' => $this->model,
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
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
        ];
    }

    private function handleRequestException(RequestException $e, string $context): never
    {
        Log::error(sprintf('OpenRouter API %s request failed', $context), [
            'status' => $e->getResponse()?->getStatusCode(),
            'body' => $e->getResponse()?->getBody()?->getContents(),
        ]);

        throw new RuntimeException(
            sprintf('OpenRouter API error: %s', $e->getMessage()),
            $e->getCode(),
            $e
        );
    }

    private function parse(string $raw): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = mb_trim((string) preg_replace('/\s*```$/m', '', $clean));

        $firstBrace = mb_strpos($clean, '{');
        $lastBrace = mb_strrpos($clean, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            $clean = mb_substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $clean = $this->repairJson($clean);

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($clean, associative: true);

        if (! is_array($parsed)) {
            Log::error('Failed to parse OpenRouter response', ['raw' => $raw]);
            throw new ReviewParseException('Invalid JSON from OpenRouter: '.json_last_error_msg());
        }

        throw_unless(
            isset($parsed['summary'], $parsed['score'], $parsed['issues']),
            ReviewParseException::class,
            'OpenRouter response missing required fields'
        );

        return $this->sanitize($parsed);
    }

    private function repairJson(string $json): string
    {
        $json = (string) preg_replace('/(?<=[\s,{])(\w+)"(?=\s*:)/', '"$1"', $json);

        $json = (string) preg_replace('/"(\w+)\s+\[/', '"$1": [', $json);

        $json = (string) preg_replace('/(?<=[\s,{])(\w+)\s+"([^"]+)"/', '"$1": "$2"', $json);

        $json = (string) preg_replace('/:\s*([a-zA-Z_]\w*)"?([,}\]]|$)/', ': "$1"$2', $json);

        $json = (string) preg_replace('/,\s*"\s*([}\]])/', '$1', $json);

        $json = (string) preg_replace('/,\s*([}\]])/', '$1', $json);

        return (string) preg_replace('/:\s*,/', ': null,', $json);
    }

    private function sanitize(array $parsed): array
    {
        $parsed['score'] = is_numeric($parsed['score'] ?? null) ? (int) $parsed['score'] : 0;

        $parsed['issues'] = array_values(array_map(function (array $issue): array {
            $issue['line'] = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
            $issue['severity'] = in_array($issue['severity'] ?? null, ['critical', 'high', 'medium', 'low', 'praise'], strict: true)
                ? $issue['severity']
                : 'medium';
            $issue['message'] = $issue['message'] ?? $issue['description'] ?? $issue['title'] ?? '';

            return $issue;
        }, $parsed['issues'] ?? []));

        $parsed['highlights'] = array_values(array_filter(array_map(function (mixed $highlight): ?array {
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
        }, $parsed['highlights'] ?? []), fn (?array $h): bool => $h !== null && ($h['content'] !== '')));
        $parsed['recommendation'] ??= 'comment';
        $parsed['score_rationale'] ??= '';

        return $parsed;
        $parsed['highlights'] = is_array($parsed['highlights'] ?? null) ? $parsed['highlights'] : [];
        $parsed['recommendation'] ??= 'comment';
        $parsed['score_rationale'] ??= '';

        $encoded = json_encode($parsed);

        return ['content' => $encoded !== false ? $encoded : '{}'];
    }
}
