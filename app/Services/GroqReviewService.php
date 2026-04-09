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
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 120,
        ]);
    }

    public function review(string $systemPrompt, string $userPrompt): array
    {
        try {
            $response = $this->client->post(config('services.groq.base_url').'chat/completions', [
                'json' => [
                    'model' => config('services.groq.model'),
                    'temperature' => config('services.groq.temperature'),
                    'max_tokens' => config('services.groq.max_tokens'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.config('services.groq.api_key'),
                ],
            ]);
        } catch (RequestException $e) {
            Log::error('Groq API request failed', [
                'status' => $e->getResponse()?->getStatusCode(),
                'body' => $e->getResponse()?->getBody()?->getContents(),
            ]);

            throw new RuntimeException(
                sprintf('Groq API error: %s', $e->getMessage())
            );
        }

        $data = json_decode((string) $response->getBody(), associative: true);
        $raw = $data['choices'][0]['message']['content'] ?? '';

        return $this->parse($raw);
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): array
    {
        $fullContent = '';

        try {
            $response = $this->client->post(config('services.groq.base_url').'chat/completions', [
                'json' => [
                    'model' => config('services.groq.model'),
                    'temperature' => config('services.groq.temperature'),
                    'max_tokens' => config('services.groq.max_tokens'),
                    'stream' => true,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.config('services.groq.api_key'),
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $line = $body->read(1024);

                if (empty($line)) {
                    continue;
                }

                $lines = explode("\n", $line);

                foreach ($lines as $rawLine) {
                    if (empty($rawLine) || ! str_starts_with($rawLine, 'data: ')) {
                        continue;
                    }

                    $data = trim(mb_substr($rawLine, 6));

                    if ($data === '[DONE]') {
                        continue;
                    }

                    $json = json_decode($data, associative: true);

                    if (! isset($json['choices'][0]['delta']['content'])) {
                        continue;
                    }

                    $chunk = $json['choices'][0]['delta']['content'];
                    $fullContent .= $chunk;
                    $onChunk($chunk);
                }
            }
        } catch (RequestException $e) {
            Log::error('Groq API streaming request failed', [
                'status' => $e->getResponse()?->getStatusCode(),
                'body' => $e->getResponse()?->getBody()?->getContents(),
            ]);

            throw new RuntimeException(
                sprintf('Groq API error: %s', $e->getMessage())
            );
        }

        return $this->parse($fullContent);
    }

    private function parse(string $raw): array
    {
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```$/m', '', (string) $clean);
        $clean = mb_trim((string) $clean);

        $parsed = json_decode($clean, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
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

    private function sanitize(array $parsed): array
    {
        $parsed['score'] = (int) $parsed['score'];

        $parsed['issues'] = array_map(function (array $issue): array {
            $issue['line'] = isset($issue['line']) ? (int) $issue['line'] : null;
            $issue['severity'] = in_array($issue['severity'], [
                'critical', 'high', 'medium', 'low', 'praise',
            ], strict: true) ? $issue['severity'] : 'medium';

            return $issue;
        }, $parsed['issues'] ?? []);

        $parsed['highlights'] ??= [];
        $parsed['recommendation'] ??= 'comment';
        $parsed['score_rationale'] ??= '';

        return $parsed;
    }
}
