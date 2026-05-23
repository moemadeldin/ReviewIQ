<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GroqReviewService implements AIReviewer
{
    public function __construct(
        private Client $client,
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
    ) {}

    /**
     * @return array{content: string}
     */
    public function review(string $systemPrompt, string $userPrompt): array
    {
        $response = $this->send($systemPrompt, $userPrompt, stream: false);
        $raw = $response['choices'][0]['message']['content'] ?? '';

        throw_if($raw === '', ReviewParseException::class, 'Groq returned empty response');

        return $this->parse($raw);
    }

    /**
     * @return array{content: string}
     */
    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';

        try {
            $response = $this->client->post($this->baseUrl.'chat/completions', [
                'json' => $this->buildRequestBody($systemPrompt, $userPrompt, stream: true),
                'headers' => $this->buildHeaders(),
                'stream' => true,
                'read_timeout' => 120,
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

        throw_if($fullContent === '', ReviewParseException::class, 'Groq returned empty streaming response');

        return $this->parse($fullContent);
    }

    /**
     * @return array<string, mixed>
     */
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

        /** @var array<string, mixed> */
        return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
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

        if (! $stream) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        if ($stream) {
            $body['stream'] = true;
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
        ];
    }

    private function handleRequestException(RequestException $e, string $context): never
    {
        Log::error(sprintf('Groq API %s request failed', $context), [
            'status' => $e->getResponse()?->getStatusCode(),
            'body' => $e->getResponse()?->getBody()?->getContents(),
        ]);

        throw new RuntimeException(
            sprintf('Groq API error: %s', $e->getMessage()),
            $e->getCode(),
            $e
        );
    }

    /**
     * @return array{content: string}
     */
    private function parse(string $raw): array
    {
        $clean = mb_trim((string) preg_replace(['/^```(?:json)?\s*/m', '/\s*```$/m'], '', $raw));

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($clean, associative: true);

        if (! is_array($parsed)) {
            Log::error('Failed to parse Groq response', ['raw' => $raw]);
            throw new ReviewParseException('Invalid JSON from Groq: '.json_last_error_msg());
        }

        throw_unless(
            isset($parsed['summary'], $parsed['score'], $parsed['issues']),
            ReviewParseException::class,
            'Groq response missing required fields'
        );

        return $this->sanitize($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{content: string}
     */
    private function sanitize(array $parsed): array
    {
        $parsed['score'] = is_numeric($parsed['score'] ?? null) ? (int) $parsed['score'] : 0;

        $parsed['issues'] = array_values(array_map(function (array $issue): array {
            $issue['line'] = isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null;
            $issue['severity'] = in_array($issue['severity'] ?? null, ['critical', 'high', 'medium', 'low', 'praise'], strict: true)
                ? $issue['severity']
                : 'medium';

            return $issue;
        }, $parsed['issues'] ?? []));

        $parsed['highlights'] = is_array($parsed['highlights'] ?? null) ? $parsed['highlights'] : [];
        $parsed['recommendation'] ??= 'comment';
        $parsed['score_rationale'] ??= '';

        $encoded = json_encode($parsed);

        return ['content' => $encoded !== false ? $encoded : '{}'];
    }
}
