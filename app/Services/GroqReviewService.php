<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GroqReviewService implements AIReviewer
{
    public function review(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::withToken(config('services.groq.api_key'))
            ->timeout(60)
            ->post(config('services.groq.base_url').'/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => config('services.groq.temperature'),
                'max_tokens' => config('services.groq.max_tokens'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Groq API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                sprintf('Groq API error %d: %s', $response->status(), $response->body())
            );
        }

        $raw = $response->json('choices.0.message.content') ?? '';

        return $this->parse($raw);
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
