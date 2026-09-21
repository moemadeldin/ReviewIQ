<?php

declare(strict_types=1);

use App\Exceptions\ReviewParseException;
use App\Services\OpenRouterReviewService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

beforeEach(function (): void {
    Config::set('services.openrouter.base_url', 'https://openrouter.ai/api/v1/');
    Config::set('services.openrouter.api_key', 'test-api-key');
    Config::set('services.openrouter.model', 'deepseek/deepseek-v4-flash-0731:free');
    Config::set('services.openrouter.temperature', 0.2);
    Config::set('services.openrouter.max_tokens', 2000);
    Log::spy();
});

function createOpenRouterService(Client $client, array $fallbackModels = []): OpenRouterReviewService
{
    return new OpenRouterReviewService(
        $client,
        config('services.openrouter.base_url'),
        config('services.openrouter.api_key'),
        config('services.openrouter.model'),
        (float) config('services.openrouter.temperature'),
        (int) config('services.openrouter.max_tokens'),
        (int) config('services.openrouter.timeout', 60),
        (int) config('services.openrouter.connect_timeout', 10),
        $fallbackModels,
    );
}

function openRouterValidJsonResponse(): string
{
    return json_encode([
        'summary' => 'Good code',
        'score' => 85,
        'score_rationale' => 'Well written',
        'issues' => [],
        'highlights' => ['Clean code'],
        'recommendation' => 'approve',
    ]);
}

it('performs non-streaming review successfully', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => openRouterValidJsonResponse()]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system prompt', 'user prompt');

    expect($result['summary'])->toBe('Good code')
        ->and($result['score'])->toBe(85);
});

it('performs streaming review successfully', function (): void {
    $chunks = [
        "data: {\"choices\":[{\"delta\":{\"content\":\"{\\\"summary\\\":\\\"Good code\\\",\\\"score\\\":85,\\\"score_rationale\\\":\\\"Well\\\",\\\"issues\\\":[],\\\"highlights\\\":[],\\\"recommendation\\\":\\\"approve\\\"}\"}}]}\n\n",
        "data: [DONE]\n\n",
    ];

    $readIndex = 0;

    $stream = $this->createMock(StreamInterface::class);
    $stream->method('eof')
        ->willReturnCallback(function () use ($chunks, &$readIndex): bool {
            return $readIndex >= count($chunks);
        });
    $stream->method('read')
        ->willReturnCallback(function () use ($chunks, &$readIndex): string {
            $chunk = $chunks[$readIndex] ?? '';
            $readIndex++;

            return $chunk;
        });

    $response = $this->createMock(Response::class);
    $response->method('getBody')->willReturn($stream);

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $chunksReceived = '';
    $result = $service->stream(
        systemPrompt: 'test',
        userPrompt: 'test',
        onChunk: function (string $chunk) use (&$chunksReceived): void {
            $chunksReceived .= $chunk;
        },
    );

    expect($result['summary'])->toBe('Good code');
    expect($chunksReceived)->toContain('Good code');
    expect($chunksReceived)->toContain('approve');
    expect($result['summary'])->toBe('Good code');
});

it('throws on http failure during review', function (): void {
    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willThrowException(new RequestException(
            message: 'Connection timeout',
            request: new Request('POST', 'test'),
        ));

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(RuntimeException::class, 'OpenRouter API error: Connection timeout');
});

it('throws on invalid json response', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => 'not valid json']],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class);
});

it('throws with max tokens hint and logs details when content is truncated empty', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            [
                'message' => ['content' => null],
                'finish_reason' => 'length',
            ],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class, 'finish_reason: length - increase OPENROUTER_MAX_TOKENS');

    Log::assertLogged('error', fn (string $message, array $context): bool => $message === 'OpenRouter returned empty response'
        && ($context['finish_reason'] ?? null) === 'length');
});

it('throws on missing required fields', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode(['summary' => 'test'])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class, 'OpenRouter response missing required fields');
});

it('sanitizes score to int', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => '85',
                'issues' => [],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');
    expect($result['score'])->toBe(85);
});

it('provides defaults for missing optional fields', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => 50,
                'issues' => [],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');
    expect($result['highlights'])->toBe([])
        ->and($result['recommendation'])->toBe('comment')
        ->and($result['score_rationale'])->toBe('');
});

it('parses json with markdown fences', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => "```json\n".openRouterValidJsonResponse()."\n```"]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');
    expect($result['summary'])->toBe('Good code');
});

it('sanitizes issues severity', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => 70,
                'issues' => [
                    ['severity' => 'invalid', 'file' => 'test.php'],
                ],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');
    expect($result['issues'][0]['severity'])->toBe('medium');
});

it('handles streaming edge cases', function (): void {
    $rawSse = "data: {\"choices\":[{\"delta\":{\"content\":\"{\\\"summary\\\":\\\"test\\\",\\\"score\\\":50,\\\"issues\\\":[]}\"}}]}\n\n:heartbeat\n\n0\n\ndata: {\"choices\":[{\"delta\":{}}]}\n\ndata: [DONE]\n\n";

    $readCallCount = 0;
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('eof')->willReturn(false);
    $stream->method('read')
        ->willReturnCallback(function () use ($rawSse, &$readCallCount): string {
            $readCallCount++;

            return match ($readCallCount) {
                1 => $rawSse,
                default => '',
            };
        });

    $response = $this->createMock(Response::class);
    $response->method('getBody')->willReturn($stream);

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $chunksReceived = '';
    $result = $service->stream('test', 'test', function (string $chunk) use (&$chunksReceived): void {
        $chunksReceived .= $chunk;
    });

    expect($result['summary'])->toBe('test')
        ->and($result['score'])->toBe(50);
    expect($chunksReceived)->toContain('test');
});

it('throws on stream request failure', function (): void {
    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willThrowException(new RequestException(
            message: 'Stream failed',
            request: new Request('POST', 'test'),
        ));

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->stream('test', 'test', fn (string $chunk): null => null))
        ->toThrow(RuntimeException::class, 'OpenRouter API error: Stream failed');
});

it('converts string highlights to object format', function (): void {
    $raw = json_encode([
        'summary' => 'test',
        'score' => 80,
        'issues' => [],
        'highlights' => ['Clean architecture', 'Good error handling'],
        'recommendation' => 'approve',
    ]);

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['highlights'])->toHaveCount(2);
    expect($result['highlights'][0])->toHaveKey('file');
    expect($result['highlights'][0])->toHaveKey('line');
    expect($result['highlights'][0])->toHaveKey('content');
    expect($result['highlights'][0]['content'])->toBe('Clean architecture');
    expect($result['highlights'][1]['content'])->toBe('Good error handling');
});

it('repairs trailing comma with stray quote before closing brace', function (): void {
    $raw = '{
  "summary": "test",
  "score": 82,
  "issues": [],
  "highlights": [
    "Clean code"
  ],
 "}';

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['summary'])->toBe('test')
        ->and($result['score'])->toBe(82);
});

it('repairs trailing comma before closing brace', function (): void {
    $raw = '{"summary": "test", "score": 75, "issues": [],}';

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['summary'])->toBe('test')
        ->and($result['score'])->toBe(75);
});

it('rejects constructor with empty base url', function (): void {
    $client = $this->createMock(Client::class);

    expect(fn (): OpenRouterReviewService => new OpenRouterReviewService($client, '', 'key', 'model', 0.2, 2000))
        ->toThrow(InvalidArgumentException::class, 'Base URL cannot be empty.');
});

it('repairs missing colon before bracket key', function (): void {
    $raw = '{"score":70,"issues [{"severity":"high"}],"summary": "test"}';

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['issues'][0]['severity'])->toBe('high');
});

it('repairs common json malformations', function (): void {
    $raw = '{
 summary": " PR introduces significant change",
 score": 70,
 score_rationale "The score is 70",
 "issues": [
    {
 "severity":medium",
 "line":,
      "title": "Web Registration"
    }
 ],
 "recommendation": "request_changes"
}';

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['summary'])->toBe(' PR introduces significant change')
        ->and($result['score'])->toBe(70)
        ->and($result['score_rationale'])->toBe('The score is 70')
        ->and($result['issues'][0]['severity'])->toBe('medium')
        ->and($result['recommendation'])->toBe('request_changes');
});

it('parses valid json without corrupting string bodies', function (): void {
    $raw = mb_trim(json_encode([
        'summary' => 'Added coverage: filtering, pagination support',
        'score' => 72,
        'score_rationale' => 'Solid: build, fine edge: cases',
        'issues' => [
            ['severity' => 'medium', 'line' => null, 'message' => 'The triggers: pagination here'],
        ],
        'highlights' => ['clean: code, section'],
        'recommendation' => 'request_changes',
    ], flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => $raw]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);
    $result = $service->review('system', 'user');

    expect($result['summary'])->toBe('Added coverage: filtering, pagination support')
        ->and($result['score'])->toBe(72)
        ->and($result['score_rationale'])->toBe('Solid: build, fine edge: cases')
        ->and($result['issues'][0]['description'])->toBe('The triggers: pagination here')
        ->and($result['highlights'][0]['content'])->toBe('clean: code, section');
});

it('falls back to the next model when parsing fails', function (): void {
    $invalid = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => 'User Safety: safe']],
        ],
    ]));

    $valid = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => openRouterValidJsonResponse()]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->exactly(2))
        ->method('post')
        ->willReturnOnConsecutiveCalls($invalid, $valid);

    $service = createOpenRouterService($client, ['qwen/qwen3.8-27b:free']);
    $result = $service->review('system', 'user');

    expect($result['summary'])->toBe('Good code');
});

it('throws the last error when every fallback model fails', function (): void {
    $invalid = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => 'not valid json']],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->exactly(2))
        ->method('post')
        ->willReturn($invalid);

    $service = createOpenRouterService($client, ['qwen/qwen3.8-27b:free']);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class, 'Invalid JSON from OpenRouter');
});

it('reports the decoding error truthfully when json is invalid', function (): void {
    $response = new Response(Illuminate\Http\Response::HTTP_OK, [], json_encode([
        'choices' => [
            ['message' => ['content' => '{ this is not json "']],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = createOpenRouterService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class, 'Invalid JSON from OpenRouter: Syntax error');
});
