<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIReviewer;
use App\Exceptions\ReviewParseException;
use App\Utilities\Constants;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class OpenRouterReviewService implements AIReviewer
{
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
        $parser = new ReviewResponseParser();

        foreach ($this->models() as $model) {
            for ($attempt = 0; $attempt <= Constants::AI_MAX_FALLBACK_RETRIES; $attempt++) {
                try {
                    $result = $this->attemptReview($model, $systemPrompt, $userPrompt, $parser);

                    return array_merge($result, ['meta' => [
                        'model' => $model,
                        'usage' => $result['usage'] ?? null,
                    ]]);
                } catch (ReviewParseException|RuntimeException|JsonException $error) {
                    $lastError = $error;

                    $status = $this->extractStatus($error);
                    $isRetryable = $this->isRetryableStatus($status);

                    if ($attempt < Constants::AI_MAX_FALLBACK_RETRIES && $isRetryable) {
                        $delay = Constants::AI_RETRY_DELAY_BASE_MS * 2 ** $attempt; // 1s, 2s
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
        $parser = new ReviewResponseParser();

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

                $reader->read($body, function (string $line) use (&$fullContent, &$firstChunkEmitted, $onChunk): void {
                    if (! str_starts_with($line, 'data: ')) {
                        return;
                    }

                    $data = mb_trim(mb_substr($line, 6));

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

                throw_if($fullContent === '', ReviewParseException::class, 'OpenRouter returned empty streaming response');

                $parsed = $parser->parse($fullContent, $model);

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

    private function attemptReview(string $model, string $systemPrompt, string $userPrompt, ReviewResponseParser $parser): array
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
                'response_length' => mb_strlen((string) json_encode($response)),
            ]);

            $hint = $finishReason === 'length'
                ? ' (finish_reason: length - increase OPENROUTER_MAX_TOKENS)'
                : '';

            throw new ReviewParseException('OpenRouter returned empty response'.$hint);
        }

        $parsed = $parser->parse($raw, $model);

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
        } catch (GuzzleException $guzzleException) {
            throw new RuntimeException(
                sprintf('OpenRouter API error: %s', $guzzleException->getMessage()),
                $guzzleException->getCode(),
                $guzzleException,
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

    private function extractStatus(Throwable $e): ?int
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
}
