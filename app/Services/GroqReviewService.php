<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GroqReviewService implements AIReviewer
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 120,
        ]);
    }

    /**
     * @return array{content: string}
     */
    public function review(string $systemPrompt, string $userPrompt): array
    {
        $baseUrl = config('services.groq.base_url');
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model');
        $temperature = config('services.groq.temperature');
        $maxTokens = config('services.groq.max_tokens');

        throw_if(! is_string($baseUrl) || ! is_string($apiKey) || ! is_string($model), RuntimeException::class, 'Invalid Groq configuration');

        try {
            /** @var Response $response */
            $response = $this->client->post($baseUrl.'chat/completions', [
                'json' => [
                    'model' => $model,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                ],
            ]);
        } catch (RequestException $requestException) {
            Log::error('Groq API request failed', [
                'status' => $requestException->getResponse()?->getStatusCode(),
                'body' => $requestException->getResponse()?->getBody()?->getContents(),
            ]);

            throw new RuntimeException(sprintf('Groq API error: %s', $requestException->getMessage()), $requestException->getCode(), $requestException);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $response->getBody(), associative: true);
        $raw = '';

        if (is_array($data) && isset($data['choices']) && is_array($data['choices']) && ($data['choices']) !== []) {
            $firstChoice = $data['choices'][0] ?? null;
            if (is_array($firstChoice) && isset($firstChoice['message']) && is_array($firstChoice['message']) && isset($firstChoice['message']['content'])) {
                /** @var string $raw */
                $raw = $firstChoice['message']['content'];
            }
        }

        return $this->parse($raw);
    }

    /**
     * @return array{content: string}
     */
    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';

        $baseUrl = config('services.groq.base_url');
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model');
        $temperature = config('services.groq.temperature');
        $maxTokens = config('services.groq.max_tokens');

        throw_if(! is_string($baseUrl) || ! is_string($apiKey) || ! is_string($model), RuntimeException::class, 'Invalid Groq configuration');

        try {
            /** @var Response $response */
            $response = $this->client->post($baseUrl.'chat/completions', [
                'json' => [
                    'model' => $model,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'stream' => true,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $line = $body->read(1024);
                if ($line === '') {
                    continue;
                }

                if ($line === '0') {
                    continue;
                }

                $lines = explode("\n", $line);

                foreach ($lines as $rawLine) {
                    if ($rawLine === '') {
                        continue;
                    }

                    if ($rawLine === '0') {
                        continue;
                    }

                    if (! str_starts_with($rawLine, 'data: ')) {
                        continue;
                    }

                    $data = mb_trim(mb_substr($rawLine, 6));

                    if ($data === '[DONE]') {
                        continue;
                    }

                    /** @var array{choices: array<int, array{delta: array{content: string}}>}|null $json */
                    $json = json_decode($data, associative: true);

                    if (! isset($json['choices'][0]['delta']['content'])) {
                        continue;
                    }

                    /** @var string $chunk */
                    $chunk = $json['choices'][0]['delta']['content'];
                    $fullContent .= $chunk;
                    $onChunk($chunk);
                }
            }
        } catch (RequestException $requestException) {
            Log::error('Groq API streaming request failed', [
                'status' => $requestException->getResponse()?->getStatusCode(),
                'body' => $requestException->getResponse()?->getBody()?->getContents(),
            ]);

            throw new RuntimeException(sprintf('Groq API error: %s', $requestException->getMessage()), $requestException->getCode(), $requestException);
        }

        return $this->parse($fullContent);
    }

    /**
     * @return array{content: string}
     */
    private function parse(string $raw): array
    {
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```$/m', '', (string) $clean);
        $clean = mb_trim((string) $clean);

        /** @var array<string, mixed>|false $parsed */
        $parsed = json_decode($clean, associative: true);

        if (! is_array($parsed)) {
            Log::error('Failed to parse Groq response', ['raw' => $raw]);
            throw new ReviewParseException('Invalid JSON from Groq: '.json_last_error_msg());
        }

        throw_unless(isset($parsed['summary'], $parsed['score'], $parsed['issues']), ReviewParseException::class, 'Groq response missing required fields');

        return $this->sanitize($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{content: string}
     */
    private function sanitize(array $parsed): array
    {
        /** @var mixed $scoreValue */
        $scoreValue = $parsed['score'] ?? 0;
        $parsed['score'] = is_numeric($scoreValue) ? (int) $scoreValue : 0;

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $parsed['issues'] ?? [];
        $parsed['issues'] = array_values(array_map(function (array $issue): array {
            $line = $issue['line'] ?? null;
            $issue['line'] = $line !== null && is_numeric($line) ? (int) $line : null;
            $severity = $issue['severity'] ?? null;
            $issue['severity'] = is_string($severity) && in_array($severity, [
                'critical', 'high', 'medium', 'low', 'praise',
            ], strict: true) ? $severity : 'medium';

            return $issue;
        }, $issues));

        if (! isset($parsed['highlights']) || ! is_array($parsed['highlights'])) {
            $parsed['highlights'] = [];
        }

        if (! isset($parsed['recommendation'])) {
            $parsed['recommendation'] = 'comment';
        }

        if (! isset($parsed['score_rationale'])) {
            $parsed['score_rationale'] = '';
        }

        /** @var non-empty-string|false $jsonEncoded */
        $jsonEncoded = json_encode($parsed);
        $content = $jsonEncoded !== false ? $jsonEncoded : '{}';

        return ['content' => $content];
    }
}
